// ESC/POS: the command language almost every thermal receipt printer
// understands, whatever its brand.
import { layout } from './layout.js';

const ESC = 0x1b;
const GS = 0x1d;

// Printers take single-byte text; anything outside Latin-1 becomes '?'.
function encode(text) {
  return Buffer.from(String(text).replace(/[^\x00-\xff]/g, '?'), 'latin1');
}

export function toEscPos(document, paperWidth) {
  const parts = [Buffer.from([ESC, 0x40])]; // Initialise.

  for (const line of layout(document, paperWidth)) {
    if (line.cut) {
      parts.push(Buffer.from([GS, 0x56, 0x42, 0x00])); // Feed and cut.
      continue;
    }

    parts.push(Buffer.from([ESC, 0x45, line.bold ? 1 : 0])); // Bold on or off.
    parts.push(Buffer.from([GS, 0x21, line.size === 'large' ? 0x11 : 0x00])); // Double size or normal.
    parts.push(encode(line.text), Buffer.from([0x0a]));
  }

  parts.push(Buffer.from([ESC, 0x45, 0, GS, 0x21, 0x00]));

  return Buffer.concat(parts);
}
