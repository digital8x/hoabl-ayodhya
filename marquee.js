const fs = require('fs');
let html = fs.readFileSync('index.html', 'utf8');

const startMarker = '<div class="amenities-grid reveal"';
const startIndex = html.indexOf(startMarker);
const endMarker = '  </section>\r\n\r\n  <!-- GALLERY -->';
let endIndex = html.indexOf(endMarker, startIndex);
if (endIndex === -1) {
    endIndex = html.indexOf('  </section>\n\n  <!-- GALLERY -->', startIndex);
}

const sectionContent = html.substring(startIndex, endIndex);

// Split the items
const items = sectionContent.split('<div class="amen-item">');
// items[0] is the container opening tag
const itemsOnly = items.slice(1);

const half = Math.ceil(itemsOnly.length / 2);
const firstHalf = itemsOnly.slice(0, half).map(i => '<div class="amen-item marquee-item">' + i).join('');
const secondHalf = itemsOnly.slice(half).map(i => '<div class="amen-item marquee-item">' + i).join('');

const newHTML = \
    <style>
      .marquee-wrapper {
        overflow: hidden;
        width: 100%;
        position: relative;
        display: flex;
        margin-bottom: 2rem;
        -webkit-mask-image: linear-gradient(to right, transparent, black 5%, black 95%, transparent);
        mask-image: linear-gradient(to right, transparent, black 5%, black 95%, transparent);
      }
      .marquee-track {
        display: flex;
        gap: 1.5rem;
        width: max-content;
      }
      .marquee-track.left {
        animation: scrollLeft 45s linear infinite;
      }
      .marquee-track.right {
        animation: scrollRight 45s linear infinite;
      }
      .marquee-track:hover {
        animation-play-state: paused;
      }
      @keyframes scrollLeft {
        0% { transform: translateX(0); }
        100% { transform: translateX(-50%); }
      }
      @keyframes scrollRight {
        0% { transform: translateX(-50%); }
        100% { transform: translateX(0); }
      }
      .marquee-item {
        width: 280px !important;
        flex-shrink: 0;
        white-space: normal;
        margin-bottom: 0 !important;
      }
    </style>

    <div class="marquee-wrapper reveal">
      <div class="marquee-track left" id="marquee1">
        \
      </div>
    </div>
    
    <div class="marquee-wrapper reveal">
      <div class="marquee-track right" id="marquee2">
        \
      </div>
    </div>
    
    <script>
      // Duplicate items for infinite seamless scroll
      document.getElementById('marquee1').innerHTML += document.getElementById('marquee1').innerHTML;
      
      // For right scrolling, we need to start at -50% and go to 0, so the content needs to be duplicated
      document.getElementById('marquee2').innerHTML += document.getElementById('marquee2').innerHTML;
    </script>
\;

html = html.substring(0, startIndex) + newHTML + html.substring(endIndex);
fs.writeFileSync('index.html', html);
console.log("Success");
