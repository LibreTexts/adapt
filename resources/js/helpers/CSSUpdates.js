export const h5pOnLoadCssUpdates = {
  elements: [
    {
      selector: '.h5p-content',
      style: 'border:none;'
    }
  ]
}

export var webworkOnLoadCssUpdates = {
  elements: [
    {
      selector: 'div#problem_body',
      style: 'padding-top:0px;background: none;border: none;box-shadow: none'
      // EK: font portion of this style used to be a module-level constant
      // baked in from this bundle's own ambient window.self !== window.top -
      // correct for questions.view (which always runs at the app's true
      // top level, LTI-embedded or not), but wrong for the tree editor,
      // which is *always* one iframe deeper (the tree modal itself)
      // regardless of whether the app is LTI-embedded, so it always saw
      // itself as "embedded" even when the app wasn't. See
      // setWebworkProblemBodyFont() below - HandleTechnologyResponse.js
      // now calls it with the app-level embedding status computed per
      // caller, so the tree modal matches whatever questions.view shows.
    },
    {
      selector: '.attemptResultsHeader',
      style: 'display:none'
    },
    {
      selector: 'table.attemptResults',
      style: 'display:none'
    },
    {
      selector: 'div.attemptResultsSummary',
      style: 'display:none'
    },
    {
      selector: 'input[name="submitAnswers"]',
      class: 'btn btn-sm btn-primary'
    },
    {
      selector: 'input[name="previewAnswers"]',
      style: 'display:none'
    },
    {
      selector: 'a.knowls',
      style: 'visibility:hidden'
    },
    {
      selector: '.solution',
      style: 'display:none'
    }
  ],
  templates: [
    '.btn-primary:not(:hover) {background-color: #0058E6 !important;}',
    '.btn-primary:hover, .btn-primary:focus {color: #0058E6 !important;background-color: white !important;}',
    'p>a.knowl {display:none;}'],
  showSolutions: true
}

export function setWebworkProblemBodyFont (isEmbedded) {
  const iframeTextType = isEmbedded ? ';font-size:17.6px;font-weight:400;color:#000000;font-family:Tahoma, Ariel, serif' : ''
  const problemBodyElement = webworkOnLoadCssUpdates.elements.find(el => el.selector === 'div#problem_body')
  if (problemBodyElement) {
    problemBodyElement.style = 'padding-top:0px;background: none;border: none;box-shadow: none' + iframeTextType
  }
}

export const h5pStudentCssUpdates = {
  elements: [
    {
      selector: '.h5p-question-check-answer',
      style: 'pointer-events: none;opacity: 0.5 !important'
    }
  ]
}

export const webworkStudentCssUpdates = {
  elements: [
    {
      selector: 'input[name="submitAnswers"]',
      style: 'pointer-events: none;opacity: 0.5 !important'
    }
  ],
  templates: [
    '.btn-primary:not(:hover) {background-color: #0058E6 !important;}',
    '.btn-primary:hover, .btn-primary:focus {color: #0058E6 !important;background-color: white !important;}'
  ]
}

export function applyWarningsVisibility (user) {
  const showWarnings = user && (user.is_webwork_macro_editor || user.role === 5)
  if (!showWarnings) {
    webworkOnLoadCssUpdates.elements.push({ selector: '.Warnings', style: 'display:none' })
  }
}
