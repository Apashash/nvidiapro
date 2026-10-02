const VALIDATED_DEPOSIT_REQUIRED_MESSAGE =
  'Vous devez effectuer au moins un dépôt confirmé avant de pouvoir effectuer un retrait.';
const PURCHASED_ACTION_REQUIRED_MESSAGE =
  'Vous devez acheter au moins une action avant de pouvoir effectuer un retrait.';

function getWithdrawalPrerequisiteMessage({ hasValidatedDeposit, hasPurchasedAction }) {
  if (!hasValidatedDeposit) return VALIDATED_DEPOSIT_REQUIRED_MESSAGE;
  if (!hasPurchasedAction) return PURCHASED_ACTION_REQUIRED_MESSAGE;
  return null;
}

module.exports = {
  getWithdrawalPrerequisiteMessage,
  VALIDATED_DEPOSIT_REQUIRED_MESSAGE,
  PURCHASED_ACTION_REQUIRED_MESSAGE,
};