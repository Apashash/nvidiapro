const { randomInt } = require('crypto');

const PROBABILITY_SCALE = 100;

function asNumber(value) {
  const number = typeof value === 'string' ? Number(value.replace(',', '.')) : Number(value);
  return Number.isFinite(number) ? number : NaN;
}

function normalizeTier(tier, index, allowDecimalFixed) {
  const min = asNumber(tier.min ?? tier.montant_min ?? tier.minAmount);
  const max = asNumber(tier.max ?? tier.montant_max ?? tier.maxAmount);
  const probability = asNumber(tier.probability ?? tier.probabilite ?? tier.chance);
  const decimalFixedAmount = allowDecimalFixed && Number.isFinite(min) && min === max;

  if ((!Number.isInteger(min) && !decimalFixedAmount) || min < 0) {
    throw new Error(`Le montant minimum de la tranche ${index + 1} doit être un entier positif ou nul.`);
  }
  if ((!Number.isInteger(max) && !decimalFixedAmount) || max < min) {
    throw new Error(`Le montant maximum de la tranche ${index + 1} doit être supérieur ou égal au minimum.`);
  }
  if (!Number.isFinite(probability) || probability < 0 || probability > PROBABILITY_SCALE) {
    throw new Error(`La probabilité de la tranche ${index + 1} doit être comprise entre 0 et 100 %.`);
  }
  if (Math.round(probability * 100) / 100 !== probability) {
    throw new Error(`La probabilité de la tranche ${index + 1} doit avoir au maximum 2 décimales.`);
  }

  return { min, max, probability };
}

function validatePrizeTiers(rawTiers) {
  if (!Array.isArray(rawTiers) || rawTiers.length === 0) {
    throw new Error('Ajoutez au moins une tranche de gain.');
  }

  const allowDecimalFixed = rawTiers.length === 1;
  const tiers = rawTiers.map((tier, index) => normalizeTier(tier, index, allowDecimalFixed))
    .sort((a, b) => a.min - b.min || a.max - b.max);
  for (let index = 1; index < tiers.length; index += 1) {
    if (tiers[index].min <= tiers[index - 1].max) {
      throw new Error('Les tranches de gain ne doivent pas se chevaucher.');
    }
  }

  const totalProbability = tiers.reduce((sum, tier) => sum + tier.probability, 0);
  if (Math.round(totalProbability * 100) !== PROBABILITY_SCALE * 100) {
    throw new Error(`La somme des probabilités doit être exactement de ${PROBABILITY_SCALE} %.`);
  }

  return tiers;
}

function parsePrizeTiers(raw, fallbackAmount = 0) {
  if (raw) {
    try {
      const parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
      return validatePrizeTiers(parsed);
    } catch (error) {
      if (Array.isArray(raw) || (typeof raw === 'string' && raw.trim().startsWith('['))) {
        throw error;
      }
    }
  }

  const amount = asNumber(fallbackAmount);
  if (!Number.isFinite(amount) || amount < 0) {
    throw new Error('Le montant fixe du code cadeau est invalide.');
  }
  return [{ min: amount, max: amount, probability: PROBABILITY_SCALE }];
}

function secureUnitRandom() {
  return randomInt(0, 1_000_000) / 1_000_000;
}

function drawPrize(rawTiers, random = secureUnitRandom) {
  const tiers = Array.isArray(rawTiers) ? validatePrizeTiers(rawTiers) : parsePrizeTiers(rawTiers);
  const roll = random() * PROBABILITY_SCALE;
  let cursor = 0;
  let selected = tiers[tiers.length - 1];

  for (const tier of tiers) {
    cursor += tier.probability;
    if (roll < cursor) {
      selected = tier;
      break;
    }
  }

  const amount = selected.min === selected.max
    ? selected.min
    : selected.min + Math.floor(random() * (selected.max - selected.min + 1));

  return { amount, tier: selected };
}

module.exports = {
  parsePrizeTiers,
  validatePrizeTiers,
  drawPrize,
};