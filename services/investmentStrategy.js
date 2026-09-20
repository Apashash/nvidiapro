const DEFAULT_TIERS = [
  { min: 0, max: 6000, rate: 5 },
  { min: 6001, max: 15000, rate: 7 },
  { min: 15001, max: 40000, rate: 10 },
  { min: 40001, max: 170000, rate: 15 },
  { min: 170001, max: 10000000, rate: 20 },
  // No higher tier was specified: keep the highest published rate above 10M.
  { min: 10000001, max: null, rate: 20 },
];

function parseStrategy(raw) {
  if (!raw) return DEFAULT_TIERS;
  try {
    const parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
    if (!Array.isArray(parsed) || parsed.length === 0) return DEFAULT_TIERS;
    return parsed
      .map((tier) => ({
        min: Number(tier.min),
        max: tier.max == null ? null : Number(tier.max),
        rate: Number(tier.rate),
      }))
      .filter((tier) => Number.isFinite(tier.min) && Number.isFinite(tier.rate))
      .sort((a, b) => a.min - b.min);
  } catch {
    return DEFAULT_TIERS;
  }
}

function rateForAmount(amount, strategy) {
  const tiers = parseStrategy(strategy);
  const numericAmount = Number(amount);
  if (!Number.isFinite(numericAmount) || numericAmount < 0) return 0;

  const matching = tiers.find((tier) =>
    numericAmount >= tier.min && (tier.max == null || numericAmount <= tier.max)
  );
  if (matching) return matching.rate;

  // Some legacy strategies use integer boundaries such as 6000 then 6001.
  // For a decimal amount in that gap, keep the previous tier's rate rather
  // than unexpectedly jumping to the highest published rate.
  const previousTier = [...tiers].reverse().find((tier) => numericAmount >= tier.min);
  return previousTier ? previousTier.rate : tiers[0].rate;
}

function lowestRate(strategy) {
  return Math.min(...parseStrategy(strategy).map((tier) => tier.rate));
}

function highestRate(strategy) {
  return Math.max(...parseStrategy(strategy).map((tier) => tier.rate));
}

module.exports = {
  DEFAULT_TIERS,
  parseStrategy,
  rateForAmount,
  lowestRate,
  highestRate,
};