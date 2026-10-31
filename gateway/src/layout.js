// Turns a RasaHub print document into lines of text for a given paper
// width. Shared by the ESC/POS printer output and the text file output.

// Characters per line in the printer's standard font.
export function columnsFor(paperWidth) {
  return Number(paperWidth) === 58 ? 32 : 48;
}

// Large text is printed double width, so half as many characters fit.
function widthOf(line, columns) {
  return line.size === 'large' ? Math.floor(columns / 2) : columns;
}

// Break text into lines no longer than the width, at spaces where possible.
export function wrap(text, width) {
  const words = String(text ?? '').split(/\s+/).filter(Boolean);
  const lines = [];
  let current = '';

  for (const word of words) {
    if (word.length > width) {
      if (current) lines.push(current);
      for (let i = 0; i < word.length; i += width) lines.push(word.slice(i, i + width));
      current = '';
    } else if ((current ? current.length + 1 : 0) + word.length > width) {
      lines.push(current);
      current = word;
    } else {
      current = current ? `${current} ${word}` : word;
    }
  }

  if (current) lines.push(current);

  return lines.length ? lines : [''];
}

export function align(text, width, how) {
  const space = Math.max(0, width - text.length);
  if (how === 'center') return ' '.repeat(Math.floor(space / 2)) + text;
  if (how === 'right') return ' '.repeat(space) + text;
  return text;
}

// A label on the left and an amount on the right, the label wrapped if it
// does not fit beside the amount.
export function row(left, right, width) {
  const room = Math.max(1, width - right.length - 1);
  const labels = wrap(left, room);
  const last = labels.pop();
  return [...labels, last + ' '.repeat(Math.max(1, width - last.length - right.length)) + right];
}

// The document as a list of { text, size, bold } lines, or { cut: true }.
export function layout(document, paperWidth) {
  const columns = columnsFor(paperWidth);
  const out = [];

  for (const line of document?.lines ?? []) {
    switch (line.type) {
      case 'text': {
        const width = widthOf(line, columns);
        for (const part of wrap(line.text, width)) {
          out.push({ text: align(part, width, line.align), size: line.size, bold: !!line.bold });
        }
        break;
      }
      case 'row':
        for (const part of row(String(line.left ?? ''), String(line.right ?? ''), columns)) {
          out.push({ text: part, size: 'normal', bold: !!line.bold });
        }
        break;
      case 'rule':
        out.push({ text: '-'.repeat(columns), size: 'normal', bold: false });
        break;
      case 'feed':
        for (let i = 0; i < (line.lines ?? 1); i++) out.push({ text: '', size: 'normal', bold: false });
        break;
      case 'cut':
        out.push({ cut: true });
        break;
      default:
        // A newer kind of line this gateway does not know: skipped, so that
        // the rest still prints.
        break;
    }
  }

  return out;
}

// The document as plain text, as it would come out of the printer.
export function asText(document, paperWidth) {
  return layout(document, paperWidth)
    .map((line) => (line.cut ? '~'.repeat(columnsFor(paperWidth)) : line.text))
    .join('\n');
}
