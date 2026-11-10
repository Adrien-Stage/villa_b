/*
 * Minuteur temps réel d'un séjour en cours (Alpine.data 'bookingTimer').
 *
 * Calcule les durées écoulées et restantes/dépassées avec Temporal dans le
 * fuseau IANA de l'établissement et formate l'affichage avec Intl.DurationFormat.
 * Charge temporal-polyfill localement uniquement si Temporal manque nativement.
 */

let temporalLoader = null;

async function ensureTemporal() {
    if (typeof globalThis.Temporal !== 'undefined') {
        return globalThis.Temporal;
    }

    if (!temporalLoader) {
        temporalLoader = import('temporal-polyfill').then((module) => {
            if (typeof globalThis.Temporal === 'undefined') {
                globalThis.Temporal = module.Temporal;
            }

            return globalThis.Temporal;
        });
    }

    return temporalLoader;
}

const DURATION_OPTIONS = {
    largestUnit: 'day',
    smallestUnit: 'second',
    roundingMode: 'trunc',
};

const HAS_INTL_DURATION_FORMAT =
    typeof Intl !== 'undefined' && typeof Intl.DurationFormat === 'function';

const DURATION_FORMATTER_SHORT = HAS_INTL_DURATION_FORMAT
    ? new Intl.DurationFormat('fr', {
          style: 'narrow',
          daysDisplay: 'auto',
          hoursDisplay: 'auto',
          minutesDisplay: 'always',
          secondsDisplay: 'always',
      })
    : null;

const DURATION_FORMATTER_DAYS = HAS_INTL_DURATION_FORMAT
    ? new Intl.DurationFormat('fr', {
          style: 'narrow',
          daysDisplay: 'always',
          hoursDisplay: 'always',
          minutesDisplay: 'always',
          secondsDisplay: 'always',
      })
    : null;

function formatDuration(duration) {
    if (HAS_INTL_DURATION_FORMAT) {
        const formatter = duration.days > 0 ? DURATION_FORMATTER_DAYS : DURATION_FORMATTER_SHORT;

        return formatter.format({
            days: Math.abs(duration.days),
            hours: Math.abs(duration.hours),
            minutes: Math.abs(duration.minutes),
            seconds: Math.abs(duration.seconds),
        });
    }

    const parts = [];
    if (duration.days > 0) parts.push(`${duration.days}j`);
    if (duration.hours > 0 || duration.days > 0) parts.push(`${duration.hours}h`);
    parts.push(`${duration.minutes}m`);
    parts.push(`${duration.seconds}s`);

    return parts.join(' ');
}

function resolveZonedEndpoint(TemporalApi, timeZone, instantIso, dateStr, timeStr) {
    if (instantIso) {
        return TemporalApi.Instant.from(instantIso).toZonedDateTimeISO(timeZone);
    }

    if (dateStr && timeStr) {
        return TemporalApi.PlainDate.from(dateStr)
            .toPlainDateTime(timeStr)
            .toZonedDateTime(timeZone);
    }

    return null;
}

function computeOverstayNights(TemporalApi, checkOutZdt, now) {
    if (TemporalApi.ZonedDateTime.compare(now, checkOutZdt) <= 0) {
        return 0;
    }

    const startDay = checkOutZdt.toPlainDate();
    const currentDay = now.toPlainDate();
    let days = startDay.until(currentDay, { largestUnit: 'day' }).days;

    if (TemporalApi.ZonedDateTime.compare(checkOutZdt.add({ days }), now) < 0) {
        days += 1;
    }

    return Math.max(1, days);
}

const FCFA_NUMBER_FORMAT = new Intl.NumberFormat('fr-FR', {
    maximumFractionDigits: 0,
});

function formatFcfa(centimes) {
    const units = Math.round((Number(centimes) || 0) / 100);

    return FCFA_NUMBER_FORMAT.format(units).replace(/[\u202f\u00a0]/g, ' ');
}

function formatOverstaySummary(overstayNights, overstayAmountCentimes, isComplimentary) {
    if (overstayNights <= 0) {
        return '';
    }

    const nightLabel = `${overstayNights} nuit${overstayNights > 1 ? 's' : ''}`;
    if (isComplimentary) {
        return `Séjour offert · +${nightLabel}`;
    }

    return `+${nightLabel} · +${formatFcfa(overstayAmountCentimes)} FCFA`;
}

function syncLiveStayTargets(config, state) {
    const {
        overstayNights,
        overstayAmountCentimes,
        effectiveTotalNights,
        effectiveRoomAmountCentimes,
        effectiveSubtotalCentimes,
        effectiveTotalCentimes,
        effectiveBalanceDueCentimes,
    } = state;

    const plannedNights = Number(config.plannedNights) || 0;
    const isComplimentary = Boolean(config.isComplimentary);

    document.querySelectorAll('[data-stay-target="duration-label"]').forEach((el) => {
        el.textContent = `${effectiveTotalNights} nuit${effectiveTotalNights > 1 ? 's' : ''}`;
    });

    document.querySelectorAll('[data-stay-target="overstay-note"]').forEach((el) => {
        el.classList.toggle('hidden', overstayNights <= 0);
        if (overstayNights > 0) {
            el.textContent = `${plannedNights} prévue${plannedNights > 1 ? 's' : ''} + ${overstayNights} de dépassement`;
        }
    });

    document.querySelectorAll('[data-stay-target="folio-room-title"]').forEach((el) => {
        const roomNumber = el.dataset.roomNumber || '';
        el.textContent = roomNumber
            ? `Hébergement — ${effectiveTotalNights} nuit(s) — Chambre ${roomNumber}`
            : `Hébergement — ${effectiveTotalNights} nuit(s)`;
    });

    document.querySelectorAll('[data-stay-target="folio-room-sub"]').forEach((el) => {
        el.classList.toggle('hidden', overstayNights <= 0);
        if (overstayNights > 0) {
            const suffix = isComplimentary ? ' (Offert)' : ` (+${formatFcfa(overstayAmountCentimes)} FCFA)`;
            el.textContent = `Prévu : ${plannedNights} nuit(s) · Dépassement : +${overstayNights} nuit(s)${suffix}`;
        }
    });

    document.querySelectorAll('[data-stay-target="folio-room-qty"]').forEach((el) => {
        el.textContent = effectiveTotalNights.toFixed(2);
    });

    document.querySelectorAll('[data-stay-target="folio-room-total"]').forEach((el) => {
        el.textContent = isComplimentary ? 'Offert' : `${formatFcfa(effectiveRoomAmountCentimes)} FCFA`;
    });

    document.querySelectorAll('[data-stay-target="folio-subtotal"]').forEach((el) => {
        el.textContent = `${formatFcfa(effectiveSubtotalCentimes)} FCFA`;
    });

    let overstayRow = document.querySelector('[data-stay-target="folio-overstay-row"]');
    if (overstayNights > 0 && !overstayRow) {
        const totalsContainer = document.querySelector('[data-stay-target="folio-totals"]');
        const totalRow = document.querySelector('[data-stay-target="folio-total-row"]');
        if (totalsContainer && totalRow) {
            overstayRow = document.createElement('div');
            overstayRow.setAttribute('data-stay-target', 'folio-overstay-row');
            overstayRow.className = 'flex justify-between text-xs text-red-700 font-medium';
            const labelSpan = document.createElement('span');
            labelSpan.setAttribute('data-stay-target', 'folio-overstay-label');
            const amountSpan = document.createElement('span');
            amountSpan.setAttribute('data-stay-target', 'folio-overstay-amount');
            overstayRow.append(labelSpan, amountSpan);
            totalsContainer.insertBefore(overstayRow, totalRow);
        }
    }

    if (overstayRow) {
        overstayRow.classList.toggle('hidden', overstayNights <= 0);
        const label = overstayRow.querySelector('[data-stay-target="folio-overstay-label"]');
        const amount = overstayRow.querySelector('[data-stay-target="folio-overstay-amount"]');
        if (label) {
            label.textContent = `Dont surplus hébergement (+${overstayNights} nuit${overstayNights > 1 ? 's' : ''})`;
        }
        if (amount) {
            amount.textContent = isComplimentary ? 'Offert' : `+${formatFcfa(overstayAmountCentimes)} FCFA`;
        }
    }

    document.querySelectorAll('[data-stay-target="folio-total"]').forEach((el) => {
        el.textContent = `${formatFcfa(effectiveTotalCentimes)} FCFA`;
    });

    document.querySelectorAll('[data-stay-target="folio-balance-due"]').forEach((el) => {
        el.textContent = `${formatFcfa(effectiveBalanceDueCentimes)} FCFA`;
    });

    document.querySelectorAll('[data-stay-target="modal-consumed-balance"]').forEach((el) => {
        el.textContent = `${formatFcfa(effectiveBalanceDueCentimes)} FCFA`;
    });

    document.querySelectorAll('[data-stay-target="modal-payment-input"]').forEach((input) => {
        input.value = String(Math.ceil(effectiveBalanceDueCentimes / 100));
    });
}

function announceOverstayTransition(message) {
    const announcer = document.getElementById('booking-overstay-announcer');
    if (!announcer || !message) {
        return;
    }

    announcer.textContent = '';
    requestAnimationFrame(() => {
        announcer.textContent = message;
    });
}

export default function bookingTimer(configOrCheckIn, legacyCheckOut = null) {
    const config =
        typeof configOrCheckIn === 'object' && configOrCheckIn !== null
            ? configOrCheckIn
            : {
                  timeZone: 'Africa/Douala',
                  checkInInstant: configOrCheckIn,
                  checkOutInstant: legacyCheckOut,
                  initialOverstay: false,
                  initialOverstayNights: 0,
                  plannedNights: 0,
                  nightlyRateCentimes: 0,
                  isComplimentary: false,
                  baseRoomAmountCentimes: 0,
                  baseExtrasAmountCentimes: 0,
                  baseDiscountAmountCentimes: 0,
                  baseTotalAmountCentimes: 0,
                  baseCreditCentimes: 0,
                  baseBalanceDueCentimes: 0,
              };

    const initialOverstayNights = Number(config.initialOverstayNights) || 0;
    const initialNightlyRate = config.isComplimentary ? 0 : (Number(config.nightlyRateCentimes) || 0);
    const initialOverstayAmount = initialOverstayNights * initialNightlyRate;

    return {
        timeSpent: 'Calcul...',
        timeLeft: 'Calcul...',
        isOverstay: Boolean(config.initialOverstay),
        overstayNights: initialOverstayNights,
        overstaySummary: formatOverstaySummary(
            initialOverstayNights,
            initialOverstayAmount,
            Boolean(config.isComplimentary)
        ),
        hasInitializedState: false,
        interval: null,
        destroyed: false,
        temporal: null,
        checkInZdt: null,
        checkOutZdt: null,

        async init() {
            try {
                const TemporalApi = await ensureTemporal();
                if (this.destroyed) {
                    return;
                }

                const timeZone = config.timeZone || 'Africa/Douala';
                this.temporal = TemporalApi;
                this.checkInZdt = resolveZonedEndpoint(
                    TemporalApi,
                    timeZone,
                    config.checkInInstant,
                    config.checkInDate,
                    config.checkInTime
                );
                this.checkOutZdt = resolveZonedEndpoint(
                    TemporalApi,
                    timeZone,
                    config.checkOutInstant,
                    config.checkOutDate,
                    config.checkOutTime
                );

                this.updateTimer();
                this.hasInitializedState = true;
                this.interval = setInterval(() => this.updateTimer(), 1000);
            } catch (error) {
                console.error('[bookingTimer] Initialisation impossible :', error);
                this.timeSpent = 'Indisponible';
                this.timeLeft = 'Indisponible';
            }
        },

        destroy() {
            this.destroyed = true;
            if (this.interval) {
                clearInterval(this.interval);
                this.interval = null;
            }
        },

        updateTimer() {
            if (!this.temporal || !this.checkInZdt || !this.checkOutZdt) {
                return;
            }

            const TemporalApi = this.temporal;
            const timeZone = config.timeZone || 'Africa/Douala';
            const now = TemporalApi.Now.zonedDateTimeISO(timeZone);

            // 1. Temps passé depuis le check-in effectif (ou l'arrivée prévue).
            const spentDuration =
                TemporalApi.ZonedDateTime.compare(now, this.checkInZdt) <= 0
                    ? TemporalApi.Duration.from({ seconds: 0 })
                    : now.since(this.checkInZdt, DURATION_OPTIONS);
            this.timeSpent = formatDuration(spentDuration);

            // 2. Temps restant jusqu'au check-out ou temps de dépassement + surplus financier.
            if (TemporalApi.ZonedDateTime.compare(now, this.checkOutZdt) > 0) {
                const overstayDuration = this.checkOutZdt.until(now, DURATION_OPTIONS);
                const nextOverstayNights = computeOverstayNights(TemporalApi, this.checkOutZdt, now);
                const previousNights = this.overstayNights;

                this.isOverstay = true;
                this.timeLeft = `+ ${formatDuration(overstayDuration)}`;
                this.syncOverstayState(nextOverstayNights, previousNights);
            } else {
                const remainingDuration = now.until(this.checkOutZdt, DURATION_OPTIONS);
                const previousNights = this.overstayNights;

                this.isOverstay = false;
                this.timeLeft = formatDuration(remainingDuration);
                this.syncOverstayState(0, previousNights);
            }
        },

        syncOverstayState(nextOverstayNights, previousNights) {
            const isComplimentary = Boolean(config.isComplimentary);
            const nightlyRate = isComplimentary ? 0 : (Number(config.nightlyRateCentimes) || 0);
            const overstayAmountCentimes = nextOverstayNights * nightlyRate;

            this.overstayNights = nextOverstayNights;
            this.overstaySummary = formatOverstaySummary(
                nextOverstayNights,
                overstayAmountCentimes,
                isComplimentary
            );

            if (nextOverstayNights === previousNights && !this.hasInitializedState) {
                return;
            }

            if (nextOverstayNights === previousNights) {
                return;
            }

            const plannedNights = Number(config.plannedNights) || 0;
            const baseRoomAmountCentimes = Number(config.baseRoomAmountCentimes) || 0;
            const baseExtrasAmountCentimes = Number(config.baseExtrasAmountCentimes) || 0;
            const baseDiscountAmountCentimes = Number(config.baseDiscountAmountCentimes) || 0;
            const baseTotalAmountCentimes = Number(config.baseTotalAmountCentimes) || 0;
            const baseCreditCentimes = Number(config.baseCreditCentimes) || 0;
            const baseBalanceDueCentimes = Number(config.baseBalanceDueCentimes) || 0;

            const effectiveTotalNights = plannedNights + nextOverstayNights;
            const effectiveRoomAmountCentimes = baseRoomAmountCentimes + overstayAmountCentimes;
            const effectiveSubtotalCentimes =
                effectiveRoomAmountCentimes + baseExtrasAmountCentimes - baseDiscountAmountCentimes;
            const effectiveTotalCentimes = baseTotalAmountCentimes + overstayAmountCentimes;
            const effectiveBalanceDueCentimes = Math.max(
                0,
                baseBalanceDueCentimes + overstayAmountCentimes - baseCreditCentimes
            );

            const detail = {
                overstayNights: nextOverstayNights,
                overstayAmountCentimes,
                effectiveTotalNights,
                effectiveRoomAmountCentimes,
                effectiveSubtotalCentimes,
                effectiveTotalCentimes,
                effectiveBalanceDueCentimes,
            };

            if (typeof config.plannedNights === 'number') {
                syncLiveStayTargets(config, detail);
            }

            window.dispatchEvent(new CustomEvent('booking:overstay-change', { detail }));

            if (this.hasInitializedState && nextOverstayNights > 0) {
                const suffix = isComplimentary
                    ? 'séjour offert.'
                    : `surplus hébergement de ${formatFcfa(overstayAmountCentimes)} FCFA, nouveau solde dû ${formatFcfa(effectiveBalanceDueCentimes)} FCFA.`;
                announceOverstayTransition(
                    `Check-out dépassé de ${nextOverstayNights} nuit${nextOverstayNights > 1 ? 's' : ''}, ${suffix}`
                );
            }
        },
    };
}
