# Walk-through PDF sources

Sources of `Peer_Review_walkthrough.pdf` (attached to the GitHub release). Not part of the plugin zip.

- `walkthrough.html` and `img/` (15 screenshots, one per figure in order). Build:
  `chromium --headless=new --no-sandbox --no-pdf-header-footer --print-to-pdf=Peer_Review_walkthrough.pdf file://$PWD/walkthrough.html`
- Demo data: course Communication Skills 101, one teacher, eight students, activity "Presentations week 5" with a Content/Delivery/Slides rubric, random allocation of 3 reviews each, two students left with reviews to do. Screenshots were taken on 0.19.8 plus the phone-rubric CSS fix (0.19.9); 0.20.0 changed nothing visible, so only the intro line (version, Moodle versions, date) was updated.
