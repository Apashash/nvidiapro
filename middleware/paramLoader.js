/**
 * Express middleware — attaches app_parametres to res.locals.appParams
 * so every EJS view can access them without per-route boilerplate.
 */
const { getParams } = require('../services/params');

module.exports = async function paramLoader(req, res, next) {
  try {
    res.locals.appParams = await getParams();
  } catch (e) {
    res.locals.appParams = {};
  }
  next();
};
