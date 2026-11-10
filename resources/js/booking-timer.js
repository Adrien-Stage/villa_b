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

export default function bookingTimer(configOrCheckIn, legacyCheckOut = null) {
    const config =
        typeof configOrCheckIn === 'object' && configOrCheckIn !== null
            ? configOrCheckIn
            : {
                  timeZone: 'Africa/Douala',
                  checkInInstant: configOrCheckIn,
                  checkOutInstant: legacyCheckOut,
                  initialOverstay: false,
              };

    return {
        timeSpent: 'Calcul...',
        timeLeft: 'Calcul...',
        isOverstay: Boolean(config.initialOverstay),
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

            // 2. Temps restant jusqu'au check-out ou temps de dépassement.
            if (TemporalApi.ZonedDateTime.compare(now, this.checkOutZdt) > 0) {
                const overstayDuration = this.checkOutZdt.until(now, DURATION_OPTIONS);
                this.isOverstay = true;
                this.timeLeft = `+ ${formatDuration(overstayDuration)}`;
            } else {
                const remainingDuration = now.until(this.checkOutZdt, DURATION_OPTIONS);
                this.isOverstay = false;
                this.timeLeft = formatDuration(remainingDuration);
            }
        },
    };
}
