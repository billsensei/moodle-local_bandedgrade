"""Minimal W3C WebDriver client for chromedriver on 127.0.0.1:9515 (no selenium).

Usage: python3 wd.py URL OUT.png [WIDTH HEIGHT]   logs in as teacher1 / teacher1 (Behat: password = username) first.
"""
import base64
import json
import sys
import urllib.request

DRIVER = 'http://127.0.0.1:9515'
SITE = 'http://127.0.0.1:8001'


def call(method, path, body=None):
    req = urllib.request.Request(DRIVER + path, method=method,
                                 data=json.dumps(body if body is not None else {}).encode(),
                                 headers={'Content-Type': 'application/json'})
    return json.load(urllib.request.urlopen(req, timeout=120))['value']


class Browser:
    def __init__(self, width=1280, height=1300):
        caps = {'capabilities': {'alwaysMatch': {'goog:chromeOptions': {
            'binary': '/usr/bin/chromium',
            'args': ['--headless=new', '--no-sandbox', '--disable-gpu', f'--window-size={width},{height}']}}}}
        self.sid = call('POST', '/session', caps)['sessionId']
        call('POST', f'/session/{self.sid}/window/rect', {'width': width, 'height': height})

    def go(self, url):
        call('POST', f'/session/{self.sid}/url', {'url': url})

    def find(self, css):
        return call('POST', f'/session/{self.sid}/element', {'using': 'css selector', 'value': css})

    def element_id(self, el):
        return list(el.values())[0]

    def type(self, css, text):
        call('POST', f'/session/{self.sid}/element/{self.element_id(self.find(css))}/value', {'text': text})

    def click(self, css):
        call('POST', f'/session/{self.sid}/element/{self.element_id(self.find(css))}/click')

    def login(self, user='teacher1', password=None):
        self.go(SITE + '/login/index.php')
        self.type('#username', user)
        self.type('#password', password or user)
        self.click('#loginbtn')

    def script(self, js):
        return call('POST', f'/session/{self.sid}/execute/sync', {'script': js, 'args': []})

    def shot(self, out):
        data = call('GET', f'/session/{self.sid}/screenshot')
        open(out, 'wb').write(base64.b64decode(data))

    def close(self):
        call('DELETE', f'/session/{self.sid}')


if __name__ == '__main__':
    url, out = sys.argv[1], sys.argv[2]
    w, h = (int(sys.argv[3]), int(sys.argv[4])) if len(sys.argv) > 4 else (1280, 1300)
    b = Browser(w, h)
    try:
        b.login()
        b.go(url)
        b.shot(out)
    finally:
        b.close()
