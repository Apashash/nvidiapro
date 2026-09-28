function formatAshtechError(error) {
  const response = error?.response;

  if (response) {
    const data = response.data;
    const body = typeof data === 'string'
      ? data
      : data === undefined
        ? ''
        : JSON.stringify(data);

    return {
      provider: 'ashtechpay',
      body,
      status: response.status || null,
    };
  }

  const details = [error?.code, error?.message].filter(Boolean).join(': ');
  return {
    provider: 'ashtechpay',
    body: details || 'Aucune réponse reçue d’AshTechPay.',
    status: null,
  };
}

module.exports = { formatAshtechError };