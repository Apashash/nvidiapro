const SQLSTATE_SUMMARIES = Object.freeze({
  '22P02': 'Une valeur envoyée à la base n’a pas le format attendu.',
  '22001': 'Une valeur dépasse la longueur autorisée par un champ.',
  '23502': 'Une valeur obligatoire pour le dépôt est manquante.',
  '23503': 'Une référence nécessaire au dépôt, comme le compte utilisateur, est introuvable.',
  '23505': 'Une référence identique existe déjà dans la base.',
  '23514': 'Une règle de validation de la base a refusé les données.',
  '42501': 'Le compte de connexion à la base n’a pas le droit d’enregistrer le dépôt.',
  '42703': 'Une colonne nécessaire est absente. Vérifiez la migration sur la base utilisée par Plesk.',
  '42804': 'Le type d’une colonne ne correspond pas aux données envoyées.',
  '42P01': 'La table nécessaire est absente de la base utilisée par Plesk.',
});

function cleanText(value, maxLength = 400) {
  if (value === undefined || value === null) return null;

  const cleaned = String(value)
    .replace(/(?:postgres(?:ql)?|mysql):\/\/[^\s)]+/gi, '[URL de connexion masquée]')
    .replace(/\b(password|token|secret|api[_ -]?key)\s*[:=]\s*\S+/gi, '$1=[masqué]')
    .replace(/[\u0000-\u001f\u007f]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .slice(0, maxLength);

  return cleaned || null;
}

function cleanIdentifier(value) {
  const cleaned = cleanText(value, 120);
  return cleaned && /^[a-zA-Z0-9_.$-]+$/.test(cleaned) ? cleaned : null;
}

function cleanPostgresDetail(value) {
  return cleanText(value)
    ?.replace(/=\([^)]*\)/g, '=[valeur masquée]')
    || null;
}

function createDepotInsertDiagnostic(error, reference) {
  const code = /^[0-9A-Z]{5}$/.test(String(error?.code || ''))
    ? String(error.code)
    : 'INCONNU';

  return {
    reference: cleanText(reference, 80) || 'non disponible',
    code,
    summary: SQLSTATE_SUMMARIES[code]
      || 'La base a refusé l’enregistrement. Consultez les journaux serveur avec cette référence.',
    message: cleanText(error?.message) || 'Aucun message technique fourni par le pilote PostgreSQL.',
    detail: cleanPostgresDetail(error?.detail),
    hint: cleanText(error?.hint),
    table: cleanIdentifier(error?.table),
    column: cleanIdentifier(error?.column),
    constraint: cleanIdentifier(error?.constraint),
  };
}

function formatDepotInsertError(diagnostic) {
  return `Dépôt non enregistré : ${diagnostic.summary} Référence de diagnostic : ${diagnostic.reference}.`;
}

module.exports = {
  createDepotInsertDiagnostic,
  formatDepotInsertError,
};
