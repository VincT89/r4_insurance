/** ExcelJS uses only hashing and secure random bytes; use the same focused browser implementations without unrelated signing/ECDH dependencies. */
exports.createHash = require('create-hash');
exports.randomBytes = require('randombytes');
exports.getHashes = () => ['md5', 'rmd160', 'sha1', 'sha224', 'sha256', 'sha384', 'sha512'];
