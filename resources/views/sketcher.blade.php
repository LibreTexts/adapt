<!DOCTYPE html>
<html lang="en">
<head>
  <title>Embedded Sketcher</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    .sketcher {
      display: inline-block;
      width: 100%;
      height: 32em;
      border: 2px solid #555555;
    }
  </style>
  <script type="text/javascript"
          src="https://cdnjs.cloudflare.com/ajax/libs/iframe-resizer/4.2.11/iframeResizer.contentWindow.min.js"
  ></script>
  <script type="module" src="https://molview.libretexts.org/2026-08/api.js"></script>
  <script>
    window.addEventListener('message', receiveMessage)

    async function receiveMessage (event) {
      console.log(event)
      if (event.data.method === 'load') {
        if ((typeof event.data.structure.atoms === 'undefined' && typeof event.data.structure.bonds === 'undefined')) {
          event.data.structure = { atoms: [], bonds: [] }
        }
        const sketcher = document.getElementById('sketcher')
        const div = document.querySelector('[data-toolset]')

        await sketcher.load(event.data.structure)
        if (div) {
          const toolset = JSON.parse(div.dataset.toolset)
          console.error(toolset)
          console.error('toolset above')
          // Use the config object as needed
          const configName = div.dataset.config
          if (configName !== 'default') {
            await sketcher.set_props({
              toolset: toolset
            })
          }

          if (configName === 'show-correct') {
            await sketcher.set_props({
              style: {
                mark_colors: ['#006100', '#9C0006'],
                atom_color: 'black'
              }
            })
          }

        }

      }
      if (event.data.method === 'import') {
        const sketcher = document.getElementById('sketcher')
        await sketcher.import('smiles', event.data.smiles)

      }
      if (event.data === 'export') {
        const sketcher = document.getElementById('sketcher')
        await sketcher.save()
        await new Promise(resolve => setTimeout(resolve, 200))
        const smiles = await sketcher.export('smiles')
        event.source.postMessage({
          image_smiles: smiles
        }, '*')
      }
      if (event.data === 'save') {
        const sketcher = document.getElementById('sketcher')
        const structure = await sketcher.save()
        const smiles = await sketcher.export('smiles')
        event.source.postMessage({
          structure: structure,
          submitted_smiles: smiles
        }, '*')
      }
    }
  </script>
</head>
<body>
<div id="page">
  @php
    $toolsetMap = [
    'marker-only' => ['atom' => ['mark' => true]],
   'arrows-only' => [
    'general' => [
        'zoom'   => true,
        'undo'   => true,
        'create' => true,   // required to draw new arrow objects at all
        'select' => false,
    ],
    'atom' => [
        'label' => [
            'element' => false,
            'r_group' => false,
            'formula' => false,
            'menu' => [
                'periodic_table' => false,
                'quick' => [],
            ],
        ],
        'formal_charge'      => false,
        'lone_pairs'         => false,
        'unpaired_electrons' => false,
        'mark'               => false,
    ],
    'bond' => [
        'type' => [],
        'mark' => false,
    ],
    'arrow' => [
        'type' => [
            'forward',
            'resonance',
            'equilibrium',
            'retrosynthesis',
            'electron_single',
            'electron_pair',
        ],
    ],
    'template' => [
        'chain' => false,
        'ring'  => false,
    ],
],
    'show-correct' => [],
    'empty' => [],
    'default' => []
    ];

    $configJson = isset($toolsetMap[$configuration])
    ? json_encode($toolsetMap[$configuration])
    : null;
  @endphp

  <div data-toolset='@json((object) ($toolsetMap[$configuration] ?? []))'
       data-config="{{ $configuration }}"
  ></div>
  @php
    $sketcher_token = app()->environment('local') ? env('SKETCHER_TOKEN') : "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJpYXQiOjE3MzA4MzQxNjcsIm5hbWUiOiJMaWJyZVRleHRzIiwibGljZW5zZSI6eyJob3N0bmFtZXMiOlsibGlicmV0ZXh0cy5vcmciXSwiZmVhdHVyZXMiOlsic2tldGNoZXIiXX19.nVlAsWHy-E2bd4CRTFMafHSq9wmZdgbGeyI5R_PYK_UlN4Xw7_8Jf3ykM_JuCdyL8jwvHtjY08co_hJ6VCntPvi1VvWWyS-fbiBP9drRZPHH16cMci9HFQ8aZAtXZsRtqGTO-xUdPZ9GINRFWBHJ_RDpyPglS5UxnsI6-ZPlvgWC8OK_UMBu_cP3KH0U6UGyNePuBBsMCVkHpa115wMG2SG8OrUI32fnnPHcNqoBGQpRhD9gi1O92BfvRt2DlunfI9Gyqo7JTN-OeA743n-ds1gwYYzWQbQR9sf0w5yQ14wRkuWzE6Qpp6-FzqLbQxKDHv7OJpyAQD7dEZuyZwtZhQ";
  @endphp
  <molview-sketcher
    id="sketcher"
    class="sketcher"
    token="{{ $sketcher_token }}"
  ></molview-sketcher>
</div>
</body>
</html>
