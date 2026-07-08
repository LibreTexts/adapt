@php
  $config = [
      'appName' => config('app.name'),
      'locale' => $locale = app()->getLocale(),
      'locales' => config('app.locales'),
      'githubAuth' => config('services.github.client_id'),
      'libretextsAuth' => config('services.libretexts.client_id'),
      'isAdmin' =>\App\Helpers\Helper::isAdmin(),
      'showEnvironment' => $_COOKIE['show_environment'] ?? false,
      'environment' => config('app.env'),
      'clickerApp' => isset($_COOKIE['clicker_app']) && +$_COOKIE['clicker_app'] === 1,
      'toggleColors' => [  'checked' => '#008600', 'unchecked' => '#6c757d' ]
  ];

if (config('app.env') === 'dev' && (!isset($_COOKIE['IS_ME']) || $_COOKIE['IS_ME'] !== '1')) {
   //dd("This is the DEV site.  Please contact support.");
}
  if (!\Auth::user() && !session()->has('landing_page')){
      session(['landing_page' =>$_SERVER['REQUEST_URI']]);
  }
@endphp
  <!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="asset-url" content="{{ config('app.asset_url') }}">
  <link rel="shortcut icon" type="image/png" href="{{ asset('assets/img/favicon.png') }}"/>
  <title>{{ config('app.name') }}</title>
  <link rel="stylesheet" href="{{ mix('dist/css/app.css') }}">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/iframe-resizer/4.2.11/iframeResizer.contentWindow.js"
          integrity="sha512-RMBWitJB1ymY4l6xeYsFwoEgVCAnOWX/zL1gNwXjlUj78nZ8SVbJsZxbH/w0p2jDNraHkOW8rzQgcJ0LNSXWBA=="
          crossorigin="anonymous"
  ></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/centrifuge/5.0.1/centrifuge.min.js"></script>
</head>
<body>
<div id="app"></div>

{{-- Global configuration object --}}
<script>
  window.config = @json($config);
</script>
{{-- Load the application scripts --}}
<script src="{{ mix('dist/js/app.js') }}"></script>
<script>
  // Compute the section/chapter prefix for equation numbering
  let front = '';
  const titleHolder = document.getElementById('titleHolder');
  if (titleHolder) {
    let match = titleHolder.innerText.match(/^.*?:/);
    if (match) {
      front = match[0].split(':')[0];
      if (front.includes('.')) {
        front = front.split('.').map(s => s.includes('0') ? parseInt(s, 10) : s).join('.');
      }
      front += '.';
    }
  }
  front = front.replace(/_/g, ' ');

  // MathJax v4 config — must be set BEFORE the script tag loads
  window.MathJax = {
    options: {
      ignoreHtmlClass: "tex2jax_ignore",
      processHtmlClass: "math-tex",
      skipHtmlTags: ["mjx-container", "svg"],
      menuOptions: {
        settings: {
          zscale: "150%",
          zoom: "Double-Click",
          assistiveMml: true,
          collapsible: false,
        },
      },
    },
    output: {
      scale: 0.85,
      mtextInheritFont: false,
      displayOverflow: "linebreak",
      linebreaks: { width: "100%" },
    },
    startup: {
      pageReady: () => {
        if (window.activateBeeLine) window.activateBeeLine();
        return MathJax.startup.defaultPageReady();
      },
    },
    chtml: { matchFontHeight: true },
    tex: {
      inlineMath: [['$', '$'], ['\\(', '\\)']],
      displayMath: [['$$', '$$'], ['\\[', '\\]']],
      tags: "all",
      tagformat: {
        number: (n) => {
          if (window.InitialOffset) {
            const offset = Number(window.InitialOffset);
            if (!offset) return front + n;
            return front + (Number(n) + offset);
          }
          return front + n;
        },
      },
      macros: {
        eatSpaces: ["#1", 2, ["", " ", "\\endSpaces"]],
        PageIndex: ["{" + front.replace(/\./g, "{.}") + "\\eatSpaces#1 \\endSpaces}", 1],
        test: ["{" + front + "#1}", 1],
        mhchemrightleftharpoons: "{\\unicode{x21CC}\\,}",
        xrightleftharpoons: ["\\mhchemxrightleftharpoons[#1]{#2}", 2, ""],
      },
      packages: {
        "[+]": ["mhchem", "color", "cancel", "ams", "tagformat", "noerrors"],
      },
    },
    loader: {
      load: ["[tex]/mhchem","[tex]/tagformat", "[tex]/noerrors", "[tex]/cancel", 'a11y/assistive-mml', 'a11y/semantic-enrich'],
    },
  };
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/mathjax/3.2.2/es5/tex-mml-svg.min.js"></script>
<script>
  if (window.top === window.self) {
    const style = document.createElement('style')
    style.textContent = `
      .page-container {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
      }
      html, body {
        margin: 0;
        padding: 0;
        height: 100%;
      }
    `
    document.head.appendChild(style)
  }
  document.addEventListener('DOMContentLoaded', function (event) {
    console.log('dom loaded')
    if (window.self !== window.top) {
      console.log('fixing body')
      document.body.style.background = 'transparent'

      const observer = new ResizeObserver(() => {
        if (window.parentIFrame) {
          window.parentIFrame.size()
        }
      })
      observer.observe(document.body)
    }
  })
</script>
</body>
</html>
