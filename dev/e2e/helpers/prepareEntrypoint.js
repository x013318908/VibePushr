const fs = require('fs');
const path = require('path');

const raw = (process.env.VP_ENTRYPOINT || 'vp.php').trim();
const entrypoint = raw.replace(/^\/+/, '') || 'vp.php';

if (entrypoint === 'vp.php') {
  process.exit(0);
}

const publicDir = path.resolve(__dirname, '..', '..', '..', 'public_html');
const source = path.join(publicDir, 'vp.php');
const destination = path.join(publicDir, entrypoint);

fs.copyFileSync(source, destination);
