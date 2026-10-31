# Walk-through PDF sources

Sources of `Banded_Grade_walkthrough.pdf` (attached to each release that updates it). Not part of the plugin zip.

- `walkthrough.html` and `img/`: the text and the 11 screenshots. Build:
  `chromium --headless=new --no-sandbox --no-pdf-header-footer --print-to-pdf=Banded_Grade_walkthrough.pdf file://$PWD/walkthrough.html`
- `wd.py`: minimal WebDriver client for chromedriver on :9515. `python3 wd.py URL out.png 1280 1450` logs in as teacher1 (password = username, as Behat sets it) and saves a screenshot of the page.
- `sitename.php.txt`, `colours.php.txt`: run on the Behat site with `php` (rename to `.php` first; they are `.txt` so phpcs and phplint skip them). They set the site name to "Riverside" and the four band colours (#f4a6a6 #ffe08a #9fd3f0 #a8e0b0) of the demo quiz.
- Demo data: course Biology 101, 12 students, "Amphibians quiz" (8 questions, bands 0/3/5/7 → 0/1/2/3, "Choose the report colours myself" ticked), attempts as in Figure 6 (Lena Larsen 2 and 6 correct), and Jack Jones's score changed by hand to 2 after the first scoring. Figures 1-5 and 7-11 were taken on 0.10.1 and still match 0.10.2; only Figure 6 (badge colour) was retaken.
