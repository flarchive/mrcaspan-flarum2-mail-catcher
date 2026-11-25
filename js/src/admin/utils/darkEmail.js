/**
 * Turns an HTML email into a dark-mode version for previewing, roughly the
 * way Gmail and Outlook do for emails that have no dark styles of their own:
 * every colour the email sets keeps its hue but has its lightness flipped
 * (white backgrounds go near-black, dark text goes light). Images are left
 * alone, so logos and photos keep their real colours.
 *
 * Only colours inside <style> blocks, style="" attributes and the old
 * bgcolor/color attributes are changed, never the email's text or links.
 */

const COLOR = /#(?:[0-9a-f]{8}|[0-9a-f]{6}|[0-9a-f]{3,4})\b|rgba?\([^)]*\)|\b(?:white|black)\b/gi;

// Everything else is drawn on this, in this.
const BASE_CSS =
  '<style>html,body{background-color:#121212;color:#e3e3e3;}a{color:#8ab4f8;}</style>';

function parse(value) {
  const v = value.trim().toLowerCase();

  if (v === 'white') return [255, 255, 255, 1];
  if (v === 'black') return [0, 0, 0, 1];

  if (v[0] === '#') {
    let hex = v.slice(1);
    if (hex.length <= 4) hex = hex.replace(/./g, (c) => c + c);
    const n = (i) => parseInt(hex.slice(i, i + 2), 16);
    return [n(0), n(2), n(4), hex.length === 8 ? n(6) / 255 : 1];
  }

  const parts = v.replace(/rgba?\(|\)/g, '').split(/[\s,/]+/).filter(Boolean);
  if (parts.length < 3) return null;
  const channel = (p) => (p.endsWith('%') ? (parseFloat(p) * 255) / 100 : parseFloat(p));
  const alpha = parts[3] === undefined ? 1 : parts[3].endsWith('%') ? parseFloat(parts[3]) / 100 : parseFloat(parts[3]);
  const rgb = parts.slice(0, 3).map(channel);
  return rgb.some(isNaN) ? null : [...rgb, isNaN(alpha) ? 1 : alpha];
}

function toHsl([r, g, b]) {
  r /= 255;
  g /= 255;
  b /= 255;
  const max = Math.max(r, g, b);
  const min = Math.min(r, g, b);
  const l = (max + min) / 2;
  if (max === min) return [0, 0, l];
  const d = max - min;
  const s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
  let h;
  if (max === r) h = (g - b) / d + (g < b ? 6 : 0);
  else if (max === g) h = (b - r) / d + 2;
  else h = (r - g) / d + 4;
  return [h / 6, s, l];
}

function fromHsl([h, s, l]) {
  if (s === 0) return [l, l, l].map((x) => Math.round(x * 255));
  const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
  const p = 2 * l - q;
  const hue = (t) => {
    if (t < 0) t += 1;
    if (t > 1) t -= 1;
    if (t < 1 / 6) return p + (q - p) * 6 * t;
    if (t < 1 / 2) return q;
    if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
    return p;
  };
  return [hue(h + 1 / 3), hue(h), hue(h - 1 / 3)].map((x) => Math.round(x * 255));
}

function invert(value) {
  const color = parse(value);
  if (!color) return value;

  const [h, s, l] = toHsl(color);
  // Flip lightness, squeezed into 7%–89% so white becomes #121212, not pure black.
  const [r, g, b] = fromHsl([h, s, 0.07 + (1 - l) * 0.82]);

  return color[3] < 1 ? `rgba(${r},${g},${b},${color[3]})` : `rgb(${r},${g},${b})`;
}

// "color: #333; border: 1px solid #ddd" -> the same with colours flipped.
// Font names ("Arial Black") and urls are left alone.
const fixDeclarations = (declarations) =>
  declarations
    .split(';')
    .map((declaration) => {
      const i = declaration.indexOf(':');
      if (i < 0) return declaration;
      const property = declaration.slice(0, i);
      if (/font|content/i.test(property) || /url\(/i.test(declaration)) return declaration;
      return property + ':' + declaration.slice(i + 1).replace(COLOR, invert);
    })
    .join(';');

// Only the insides of { } blocks, never selectors like "#home-link".
const fixStylesheet = (css) => css.replace(/\{([^{}]*)\}/g, (match, declarations) => '{' + fixDeclarations(declarations) + '}');

export default function darkEmail(html) {
  const out = String(html)
    .replace(/(<style\b[^>]*>)([\s\S]*?)(<\/style>)/gi, (m, open, css, close) => open + fixStylesheet(css) + close)
    .replace(/(\sstyle\s*=\s*)(["'])([\s\S]*?)\2/gi, (m, attr, quote, css) => attr + quote + fixDeclarations(css) + quote)
    .replace(/(\s(?:bgcolor|color|text|link|vlink|alink)\s*=\s*)(["']?)(#?[0-9a-z]+)\2/gi, (m, attr, quote, value) => attr + quote + invert(value) + quote);

  return BASE_CSS + out;
}
