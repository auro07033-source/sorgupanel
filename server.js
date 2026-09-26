const express = require('express');
const fetch = require('node-fetch');
const path = require('path');
const app = express();

app.use(express.static(__dirname));

app.get('/proxy', async (req, res) => {
  const url = Buffer.from(req.query.u || '', 'base64').toString();
  if (!/^https?:\/\//i.test(url)) return res.status(400).send('Bad URL');

  const allowed = ['trt.com.tr','daioncdn.net','ercdn.net','duhnet.tv','akamaized.net','rttv.com','wurl.tv'];
  const host = new URL(url).hostname;
  if (!allowed.some(a => host.includes(a))) return res.status(403).send('Forbidden');

  let referer = 'https://www.trt1.com.tr/';
  if (host.includes('daioncdn')) referer = 'https://www.tv8.com.tr/';
  if (host.includes('ercdn'))    referer = 'https://www.showtv.com.tr/';
  if (host.includes('duhnet'))   referer = 'https://www.cnnturk.com/';

  try {
    const r = await fetch(url, {
      headers: {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0 Safari/537.36',
        'Referer': referer,
        'Origin': referer.replace(/\/$/, ''),
        'Accept': '*/*',
      }
    });

    if (!r.ok) return res.status(r.status).end();

    const buf = await r.buffer();
    const ct = r.headers.get('content-type') || 'application/octet-stream';

    res.set('Access-Control-Allow-Origin', '*');
    res.set('Content-Type', ct);
    res.set('Cache-Control', 'no-cache');

    if (ct.includes('mpegurl') || url.includes('.m3u8')) {
      const base = url.substring(0, url.lastIndexOf('/') + 1);
      const text = buf.toString().split('\n').map(l => {
        l = l.replace(/\r$/, '');
        if (!l || l.startsWith('#')) return l;
        if (!/^https?:\/\//i.test(l)) {
          if (l.startsWith('//')) l = 'https:' + l;
          else l = base + l;
        }
        return '/proxy?u=' + Buffer.from(l).toString('base64');
      }).join('\n');
      res.send(text);
    } else {
      res.send(buf);
    }
  } catch (e) {
    console.error('Proxy error:', e.message);
    res.status(502).end();
  }
});

const port = process.env.PORT || 3000;
app.listen(port, () => console.log('✅ Proxy listening on ' + port));