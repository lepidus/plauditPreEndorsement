export const STATUS_NOT_CONFIRMED = 0;
export const STATUS_CONFIRMED = 1;
export const STATUS_DENIED = 2;
export const STATUS_COMPLETED = 3;
export const STATUS_COULDNT_COMPLETE = 4;

export function getStatusLabel(status, t) {
  const labels = {
    [STATUS_NOT_CONFIRMED]: t(
      "plugins.generic.plauditPreEndorsement.endorsementNotConfirmed"
    ),
    [STATUS_CONFIRMED]: t(
      "plugins.generic.plauditPreEndorsement.endorsementConfirmed"
    ),
    [STATUS_DENIED]: t(
      "plugins.generic.plauditPreEndorsement.endorsementDeclined"
    ),
    [STATUS_COMPLETED]: t(
      "plugins.generic.plauditPreEndorsement.endorsementCompleted"
    ),
    [STATUS_COULDNT_COMPLETE]: t(
      "plugins.generic.plauditPreEndorsement.endorsementCouldntComplete"
    ),
  };
  return labels[status] || t("common.unknownError");
}

export function getStatusClass(status) {
  const classes = {
    [STATUS_NOT_CONFIRMED]: "endorsementBadge endorsementBadgeNotConfirmed",
    [STATUS_CONFIRMED]: "endorsementBadge endorsementBadgeConfirmed",
    [STATUS_DENIED]: "endorsementBadge endorsementBadgeDenied",
    [STATUS_COMPLETED]: "endorsementBadge endorsementBadgeCompleted",
    [STATUS_COULDNT_COMPLETE]: "endorsementBadge endorsementBadgeCouldntComplete",
  };
  return classes[status] || "endorsementBadge";
}

export function canSendManually(endorsement) {
  return (
    endorsement.status === STATUS_CONFIRMED ||
    endorsement.status === STATUS_COULDNT_COMPLETE
  );
}
