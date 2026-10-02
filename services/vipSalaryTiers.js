const MAX_VIP_LEVEL = 10;
const MAX_ACTIVE_REFERRALS = 1_000_000;
const MAX_SALARY_AMOUNT = 10_000_000;

function getNextVipSalaryLevel(tiers) {
  const configuredLevels = new Set(
    (Array.isArray(tiers) ? tiers : [])
      .map(tier => Number(tier.niveau))
      .filter(level => Number.isInteger(level) && level >= 1 && level <= MAX_VIP_LEVEL)
  );

  for (let level = 1; level <= MAX_VIP_LEVEL; level += 1) {
    if (!configuredLevels.has(level)) return level;
  }

  return null;
}

function validateVipSalaryTierInput(body) {
  const form = body && typeof body === 'object' ? body : {};
  const idText = String(form.id ?? '').trim();
  let id = null;

  if (idText) {
    if (!/^[1-9]\d*$/.test(idText)) return { valid: false };
    id = Number(idText);
    if (!Number.isSafeInteger(id)) return { valid: false };
  }

  let niveau = null;
  if (id === null) {
    const niveauText = String(form.niveau ?? '').trim();
    if (!/^\d+$/.test(niveauText)) return { valid: false };
    niveau = Number(niveauText);
    if (!Number.isSafeInteger(niveau) || niveau < 1 || niveau > MAX_VIP_LEVEL) {
      return { valid: false };
    }
  }

  const label = String(form.label ?? '').trim();
  if (label.length > 255) return { valid: false };

  const referralsText = String(form.filleuls_requis ?? '').trim();
  if (!/^\d+$/.test(referralsText)) return { valid: false };
  const filleuls_requis = Number(referralsText);
  if (
    !Number.isSafeInteger(filleuls_requis) ||
    filleuls_requis < 1 ||
    filleuls_requis > MAX_ACTIVE_REFERRALS
  ) {
    return { valid: false };
  }

  const amountText = String(form.montant_cadeau ?? '').trim();
  if (!/^\d+(?:\.\d{1,2})?$/.test(amountText)) return { valid: false };
  const montant_cadeau = Number(amountText);
  if (
    !Number.isFinite(montant_cadeau) ||
    montant_cadeau <= 0 ||
    montant_cadeau > MAX_SALARY_AMOUNT
  ) {
    return { valid: false };
  }

  return {
    valid: true,
    value: { id, niveau, label, filleuls_requis, montant_cadeau },
  };
}

module.exports = {
  MAX_VIP_LEVEL,
  MAX_SALARY_AMOUNT,
  getNextVipSalaryLevel,
  validateVipSalaryTierInput,
};