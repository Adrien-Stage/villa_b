<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ENTRY_FIELDS = [
        'id', 'journal_id', 'fiscal_period_id', 'entry_date', 'value_date', 'reference', 'label',
        'source_type', 'source_id', 'schema', 'posted_at', 'validated_by', 'reverses_id', 'created_at',
    ];

    private const LINE_FIELDS = [
        'id', 'journal_entry_id', 'account_code', 'label', 'debit', 'credit',
        'auxiliary_type', 'auxiliary_id', 'analytic_center', 'created_at',
    ];

    public function up(): void
    {
        if (DB::table('journal_entries')->whereNotNull('reverses_id')->groupBy('reverses_id')
            ->havingRaw('COUNT(*) > 1')->select('reverses_id')->exists()) {
            throw new RuntimeException('Plusieurs extournes historiques pointent vers la même écriture. Rapprochez ces pièces avant la migration.');
        }

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->date('value_date')->nullable();
            // Identifiant historique conservé même après suppression du compte utilisateur.
            $table->unsignedBigInteger('validated_by')->nullable();
            $table->unique('reverses_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            $this->postgres();
        } elseif (DB::getDriverName() === 'sqlite') {
            $this->sqlite();
        } else {
            throw new RuntimeException("Pilote non pris en charge par les protections du grand livre.");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER ledger_entry_guard ON journal_entries; DROP TRIGGER ledger_line_guard ON journal_entry_lines;');
            DB::unprepared('DROP FUNCTION ledger_entry_guard(); DROP FUNCTION ledger_line_guard();');
        } else {
            foreach (['entry_insert', 'entry_update', 'entry_delete', 'entry_post', 'line_insert', 'line_update', 'line_delete'] as $trigger) {
                DB::unprepared("DROP TRIGGER IF EXISTS ledger_{$trigger}_guard");
            }
        }

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropUnique(['reverses_id']);
            $table->dropColumn(['value_date', 'validated_by']);
        });
    }

    private function postgres(): void
    {
        $entryChanges = $this->changes(self::ENTRY_FIELDS, 'IS DISTINCT FROM');
        $lineChanges = $this->changes(self::LINE_FIELDS, 'IS DISTINCT FROM');
        $invalidReversal = $this->invalidReversal();
        $invalidPosting = $this->invalidPosting();

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION ledger_entry_guard() RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF OLD.posted_at IS NOT NULL THEN RAISE EXCEPTION 'Écriture validée intangible'; END IF;
                    RETURN OLD;
                END IF;

                IF TG_OP = 'INSERT' THEN
                    IF NEW.posted_at IS NOT NULL THEN RAISE EXCEPTION 'Créer les lignes avant la validation'; END IF;
                    RETURN NEW;
                END IF;

                IF OLD.posted_at IS NOT NULL THEN
                    IF ({$entryChanges}) OR (NEW.created_by IS NOT NULL AND NEW.created_by IS DISTINCT FROM OLD.created_by)
                        OR (NEW.reversed_by_id IS DISTINCT FROM OLD.reversed_by_id AND ({$invalidReversal})) THEN
                        RAISE EXCEPTION 'Écriture validée intangible';
                    END IF;
                ELSIF NEW.posted_at IS NOT NULL THEN
                    PERFORM id FROM fiscal_periods WHERE id = NEW.fiscal_period_id FOR UPDATE;
                    IF {$invalidPosting} THEN RAISE EXCEPTION 'Validation comptable refusée'; END IF;
                END IF;
                RETURN NEW;
            END
            \$\$;

            CREATE OR REPLACE FUNCTION ledger_line_guard() RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN
                IF TG_OP <> 'INSERT' THEN
                    IF TG_OP = 'DELETE' OR ({$lineChanges}) THEN
                        PERFORM id FROM journal_entries WHERE id = OLD.journal_entry_id FOR UPDATE;
                        IF EXISTS (SELECT 1 FROM journal_entries WHERE id = OLD.journal_entry_id AND posted_at IS NOT NULL) THEN
                            RAISE EXCEPTION 'Ligne comptable validée intangible';
                        END IF;
                    END IF;
                END IF;

                IF TG_OP = 'INSERT' OR (TG_OP = 'UPDATE' AND NEW.journal_entry_id IS DISTINCT FROM OLD.journal_entry_id) THEN
                    PERFORM id FROM journal_entries WHERE id = NEW.journal_entry_id FOR UPDATE;
                    IF EXISTS (SELECT 1 FROM journal_entries WHERE id = NEW.journal_entry_id AND posted_at IS NOT NULL) THEN
                        RAISE EXCEPTION 'Ligne comptable validée intangible';
                    END IF;
                END IF;

                IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
                RETURN NEW;
            END
            \$\$;

            CREATE TRIGGER ledger_entry_guard BEFORE INSERT OR UPDATE OR DELETE ON journal_entries
                FOR EACH ROW EXECUTE FUNCTION ledger_entry_guard();
            CREATE TRIGGER ledger_line_guard BEFORE INSERT OR UPDATE OR DELETE ON journal_entry_lines
                FOR EACH ROW EXECUTE FUNCTION ledger_line_guard();
            SQL);
    }

    private function sqlite(): void
    {
        $entryChanges = $this->changes(self::ENTRY_FIELDS, 'IS NOT');
        $lineChanges = $this->changes(self::LINE_FIELDS, 'IS NOT');
        $invalidReversal = $this->invalidReversal();
        $invalidPosting = $this->invalidPosting();
        $oldPosted = 'EXISTS (SELECT 1 FROM journal_entries WHERE id = OLD.journal_entry_id AND posted_at IS NOT NULL)';
        $newPosted = 'EXISTS (SELECT 1 FROM journal_entries WHERE id = NEW.journal_entry_id AND posted_at IS NOT NULL)';

        DB::unprepared(<<<SQL
            CREATE TRIGGER ledger_entry_insert_guard BEFORE INSERT ON journal_entries
            WHEN NEW.posted_at IS NOT NULL
            BEGIN SELECT RAISE(ABORT, 'Créer les lignes avant la validation'); END;

            CREATE TRIGGER ledger_entry_update_guard BEFORE UPDATE ON journal_entries
            WHEN OLD.posted_at IS NOT NULL AND (
                ({$entryChanges}) OR (NEW.created_by IS NOT NULL AND NEW.created_by IS NOT OLD.created_by)
                OR (NEW.reversed_by_id IS NOT OLD.reversed_by_id AND ({$invalidReversal}))
            )
            BEGIN SELECT RAISE(ABORT, 'Écriture validée intangible'); END;

            CREATE TRIGGER ledger_entry_delete_guard BEFORE DELETE ON journal_entries
            WHEN OLD.posted_at IS NOT NULL
            BEGIN SELECT RAISE(ABORT, 'Écriture validée intangible'); END;

            CREATE TRIGGER ledger_entry_post_guard BEFORE UPDATE OF posted_at ON journal_entries
            WHEN OLD.posted_at IS NULL AND NEW.posted_at IS NOT NULL AND ({$invalidPosting})
            BEGIN SELECT RAISE(ABORT, 'Validation comptable refusée'); END;

            CREATE TRIGGER ledger_line_insert_guard BEFORE INSERT ON journal_entry_lines
            WHEN {$newPosted}
            BEGIN SELECT RAISE(ABORT, 'Ligne comptable validée intangible'); END;

            CREATE TRIGGER ledger_line_update_guard BEFORE UPDATE ON journal_entry_lines
            WHEN ({$lineChanges}) AND ({$oldPosted} OR {$newPosted})
            BEGIN SELECT RAISE(ABORT, 'Ligne comptable validée intangible'); END;

            CREATE TRIGGER ledger_line_delete_guard BEFORE DELETE ON journal_entry_lines
            WHEN {$oldPosted}
            BEGIN SELECT RAISE(ABORT, 'Ligne comptable validée intangible'); END;
            SQL);
    }

    private function changes(array $fields, string $operator): string
    {
        return implode(' OR ', array_map(fn (string $field) => "NEW.{$field} {$operator} OLD.{$field}", $fields));
    }

    private function invalidReversal(): string
    {
        return "OLD.reversed_by_id IS NOT NULL OR NOT EXISTS (
            SELECT 1 FROM journal_entries WHERE id = NEW.reversed_by_id
                AND reverses_id = OLD.id AND posted_at IS NOT NULL
        )";
    }

    private function invalidPosting(): string
    {
        return "NOT EXISTS (
            SELECT 1 FROM fiscal_periods p JOIN fiscal_years y ON p.fiscal_year_id = y.id
            WHERE p.id = NEW.fiscal_period_id AND DATE(NEW.entry_date) BETWEEN DATE(p.starts_on) AND DATE(p.ends_on)
                AND p.locked_at IS NULL AND y.closed_at IS NULL
        ) OR EXISTS (
            SELECT 1 FROM night_audits WHERE DATE(audit_date) = DATE(NEW.entry_date) AND closed_at IS NOT NULL
        ) OR NOT EXISTS (
            SELECT 1 FROM journal_entry_lines WHERE journal_entry_id = NEW.id
            GROUP BY journal_entry_id HAVING COUNT(*) >= 2 AND SUM(debit) = SUM(credit) AND SUM(debit) > 0
        ) OR EXISTS (
            SELECT 1 FROM journal_entry_lines l LEFT JOIN accounts a ON a.code = l.account_code
            WHERE l.journal_entry_id = NEW.id AND (
                a.id IS NULL OR NOT a.is_active OR NOT a.is_postable
                OR NOT ((l.debit > 0 AND l.credit = 0) OR (l.credit > 0 AND l.debit = 0))
            )
        )";
    }
};
