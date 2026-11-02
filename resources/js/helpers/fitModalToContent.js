// The width el's nearest block-level ancestor offers its content. Inline
// wrappers (e.g. a v-html <span>) report a clientWidth of 0, so skip them.
function availableWidth (el, body) {
  let parent = el.parentElement
  while (parent !== body && !parent.clientWidth) {
    parent = parent.parentElement
  }
  const style = getComputedStyle(parent)
  return parent.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight)
}

// How far any element inside body extends past the right edge of its
// parent's content box.
function rightOverflow (body) {
  let excess = 0
  body.querySelectorAll('*').forEach(el => {
    const parent = el.parentElement
    if (!el.clientWidth || !parent.clientWidth) return
    const contentRight = parent.getBoundingClientRect().right -
      parseFloat(getComputedStyle(parent).paddingRight)
    excess = Math.max(excess, el.getBoundingClientRect().right - contentRight)
  })
  return excess
}

// Widens a modal so wide content (MathJax matrices, tables, images) fits,
// up to 95% of the viewport. Anything wider scrolls horizontally.
export function fitModalToContent (modalEl) {
  const dialog = modalEl.querySelector('.modal-dialog')
  const body = modalEl.querySelector('.modal-body')
  if (!dialog || !body) return

  // Reset to the modal's default size before measuring.
  dialog.style.maxWidth = ''
  body.style.overflowX = ''

  // scrollWidth includes overflowing content, even inside overflow:auto containers.
  let contentWidth = body.scrollWidth
  body.querySelectorAll('mjx-container, .MathJax_Display, .MJXc-display, .MathJax_SVG_Display, table, img, .mt-section > *')
    .forEach(el => {
      // Everything between the element's container and the body's edges
      // (body padding plus any padded wrappers) also has to fit.
      const chrome = body.clientWidth - availableWidth(el, body)
      contentWidth = Math.max(contentWidth, el.scrollWidth + chrome)
    })

  if (contentWidth <= body.clientWidth) return // already fits

  const maxAllowed = window.innerWidth * 0.95
  const setWidth = width => {
    dialog.style.maxWidth = `${Math.ceil(Math.min(width, maxAllowed))}px`
  }
  // +4 accounts for modal-content borders and rounding
  setWidth(contentWidth + 4)
  body.style.overflowX = 'auto'

  // Some question layouts still push past their container at the new width,
  // which eats the right padding. Widen by that amount so both sides match.
  const excess = rightOverflow(body)
  if (excess > 1) setWidth(dialog.getBoundingClientRect().width + excess)
}
