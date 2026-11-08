/*
 * AES-128-CTR in plain JavaScript — for protected album videos when the
 * browser's own crypto (WebCrypto, crypto.subtle) is missing. Browsers
 * give WebCrypto only to HTTPS pages, so it is absent on plain http (e.g.
 * testing on a phone over Wi-Fi at http://192.168.x.x) and in some old
 * phone browsers. Same result as the server (app/Core/Media.php,
 * openssl aes-128-ctr): counter = 8-byte prefix ‖ 64-bit block number.
 *
 *   var ctr = TYTAesCtr(keyBytes16, prefixBytes8);
 *   ctr(bytes, byteOffset) → Uint8Array (decrypted; byteOffset % 16 === 0)
 *
 * Table-based AES (after the well-known SJCL construction); CTR needs
 * only the forward cipher.
 */
(function () {
  'use strict';
  var T0 = [], T1 = [], T2 = [], T3 = [], SBOX = [];
  (function tables() {
    var d = [], th = [], x, xInv, x2, x4, s, t, i, T = [T0, T1, T2, T3];
    for (i = 0; i < 256; i++) th[(d[i] = i << 1 ^ (i >> 7) * 283) ^ i] = i;
    for (x = xInv = 0; !SBOX[x]; x ^= x2 || 1, xInv = th[xInv] || 1) {
      s = xInv ^ xInv << 1 ^ xInv << 2 ^ xInv << 3 ^ xInv << 4;
      s = s >> 8 ^ s & 255 ^ 99;
      SBOX[x] = s;
      x4 = d[x2 = d[x]];
      t = d[s] * 0x101 ^ s * 0x1010100;
      for (i = 0; i < 4; i++) T[i][x] = t = t << 24 ^ t >>> 8;
    }
  })();

  function expand(key) {
    var k = [], i, tmp, rcon = 1;
    for (i = 0; i < 4; i++) k[i] = key[i * 4] << 24 | key[i * 4 + 1] << 16 | key[i * 4 + 2] << 8 | key[i * 4 + 3];
    for (i = 4; i < 44; i++) {
      tmp = k[i - 1];
      if (i % 4 === 0) {
        tmp = SBOX[tmp >>> 24] << 24 ^ SBOX[tmp >> 16 & 255] << 16 ^ SBOX[tmp >> 8 & 255] << 8 ^ SBOX[tmp & 255];
        tmp = tmp << 8 ^ tmp >>> 24 ^ rcon << 24;
        rcon = rcon << 1 ^ (rcon >> 7) * 283;
      }
      k[i] = k[i - 4] ^ tmp;
    }
    return new Int32Array(k);
  }

  window.TYTAesCtr = function (key, prefix) {
    var K = expand(key);
    var p0 = prefix[0] << 24 | prefix[1] << 16 | prefix[2] << 8 | prefix[3];
    var p1 = prefix[4] << 24 | prefix[5] << 16 | prefix[6] << 8 | prefix[7];
    var ks = new Uint8Array(16), kw = new DataView(ks.buffer);
    return function (data, offset) {
      var out = new Uint8Array(data.length), n = offset / 16, pos = 0;
      while (pos < data.length) {
        // counter block: prefix ‖ n (64-bit big-endian)
        var a = p0 ^ K[0], b = p1 ^ K[1], c = Math.floor(n / 4294967296) ^ K[2], d = (n >>> 0) ^ K[3], a2, b2, c2, ki = 4, r;
        for (r = 0; r < 9; r++) {
          a2 = T0[a >>> 24] ^ T1[b >> 16 & 255] ^ T2[c >> 8 & 255] ^ T3[d & 255] ^ K[ki];
          b2 = T0[b >>> 24] ^ T1[c >> 16 & 255] ^ T2[d >> 8 & 255] ^ T3[a & 255] ^ K[ki + 1];
          c2 = T0[c >>> 24] ^ T1[d >> 16 & 255] ^ T2[a >> 8 & 255] ^ T3[b & 255] ^ K[ki + 2];
          d  = T0[d >>> 24] ^ T1[a >> 16 & 255] ^ T2[b >> 8 & 255] ^ T3[c & 255] ^ K[ki + 3];
          ki += 4; a = a2; b = b2; c = c2;
        }
        for (r = 0; r < 4; r++) {
          kw.setInt32(r * 4, SBOX[a >>> 24] << 24 ^ SBOX[b >> 16 & 255] << 16 ^ SBOX[c >> 8 & 255] << 8 ^ SBOX[d & 255] ^ K[ki++]);
          a2 = a; a = b; b = c; c = d; d = a2;
        }
        var m = Math.min(16, data.length - pos);
        for (r = 0; r < m; r++) out[pos + r] = data[pos + r] ^ ks[r];
        pos += 16; n++;
      }
      return out;
    };
  };
})();
