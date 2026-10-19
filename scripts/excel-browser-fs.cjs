/** Browser exports use writeBuffer; filesystem APIs must fail explicitly rather than pretend to read or save local files. */
const unavailable = () => { throw new Error('Accesso al filesystem non disponibile nel browser.'); };
exports.readFile = unavailable;
exports.writeFile = unavailable;
exports.createReadStream = unavailable;
exports.createWriteStream = unavailable;
exports.stat = unavailable;
