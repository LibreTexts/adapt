// Escapes literal dollar signs so MathJax's $...$ / $$...$$ delimiters
// don't try to parse plain text (account descriptions, narratives, etc.)
// as math. Safe to call on any string, including null/undefined.
export function escapeDollar (text) {
  if (text === null || text === undefined) return text
  return String(text).replace(/\$/g, '\\$')
}

