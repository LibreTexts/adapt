<!DOCTYPE html>
<html lang="en">
<head>
  <title>ReadOnly Embedded Sketcher</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    .sketcher {
      width: 100%;
      height: auto;
      aspect-ratio: 4 / 3;
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
      console.log(event.data)
      if (event.data.method === 'load') {
        const sketcher = document.getElementById('sketcher')
        await sketcher.load(event.data.structure)
        //if (event.data.style) {
          //await sketcher.set_props({
           // style: event.data.style
          //})
       // }
      }
    }
  </script>
</head>
<body>
<div id="page">
  @php
    $sketcher_token = app()->environment('local') ? env('SKETCHER_TOKEN') : "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJpYXQiOjE3MzA4MzQxNjcsIm5hbWUiOiJMaWJyZVRleHRzIiwibGljZW5zZSI6eyJob3N0bmFtZXMiOlsibGlicmV0ZXh0cy5vcmciXSwiZmVhdHVyZXMiOlsic2tldGNoZXIiXX19.nVlAsWHy-E2bd4CRTFMafHSq9wmZdgbGeyI5R_PYK_UlN4Xw7_8Jf3ykM_JuCdyL8jwvHtjY08co_hJ6VCntPvi1VvWWyS-fbiBP9drRZPHH16cMci9HFQ8aZAtXZsRtqGTO-xUdPZ9GINRFWBHJ_RDpyPglS5UxnsI6-ZPlvgWC8OK_UMBu_cP3KH0U6UGyNePuBBsMCVkHpa115wMG2SG8OrUI32fnnPHcNqoBGQpRhD9gi1O92BfvRt2DlunfI9Gyqo7JTN-OeA743n-ds1gwYYzWQbQR9sf0w5yQ14wRkuWzE6Qpp6-FzqLbQxKDHv7OJpyAQD7dEZuyZwtZhQ";
  @endphp
  <molview-sketcher id="sketcher" mode="readonly" class="sketcher" token="{{$sketcher_token}}"></molview-sketcher>
</div>
</body>
</html>
