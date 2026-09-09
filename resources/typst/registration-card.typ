// Tournament registration card — Typst port of the Blade/Chromium version.
// Renders every registrant 2-up on US-letter pages (cut in half after printing).
//
// Data is passed as a JSON file path via `--input data=<root-relative path>`:
//   { "event": "Winter 2026 Tournament",
//     "logo": "/public/img/CDKTKD_logo.svg",
//     "cards": [ <card>|null, ... ] }
// A card:
//   { "name","age","dob","sex","weight","height","address","email","phone",
//     "school","city","state","instructors","instructor_ranks","has_notes",
//     "mark": { "row": 0-5, "col": 0-6, "degree": <int|none> } | null }
// Note text does not travel on the card; it prints on its own page from
// `notes` (per division, or top-level for the flat print):
//   [ { "name","school","items": [ { "scope": "permanent"|"temporary", "text" } ] } ]

#let doc = json(sys.inputs.data)
#let logo_path = doc.at("logo", default: "/public/img/CDKTKD_logo.svg")

#set page(paper: "us-letter", margin: (x: 0.3in, top: 0.25in, bottom: 0.25in))
#set text(font: ("Arial", "Liberation Sans", "DejaVu Sans"), size: 8.5pt)

#let belt_headers = (
  "Black (2)", "Black (1)", "Brown (1,2)", "Purple (3,4)",
  "Green (5,6)", "Yellow (7,8)", "White (9,10)",
)
#let div_rows = (
  ("Mini Pee Wee", "5-8"), ("Pee Wee", "9-11"), ("Junior", "12-15"),
  ("Executive", "40+"), ("Women", "All Ages"), ("Men", "All Ages"),
)

// A labelled field with an underlined value area.
#let field(label, value, value-size: 11pt) = grid(
  columns: (auto, 1fr), column-gutter: 4pt, align: bottom,
  strong(label),
  box(width: 100%, stroke: (bottom: 0.6pt + black), inset: (left: 3pt, bottom: 1.5pt))[
    #text(size: value-size)[#value]
  ],
)

// Top "Place" bar: 1st–7th boxes, a blank, and a Place cell.
#let place_bar() = {
  let cell(body) = box(width: 100%, height: 16pt, stroke: 0.6pt + black, inset: 2pt)[#body]
  grid(
    columns: (1fr,) * 7 + (2fr, 3fr),
    ..("1st","2nd","3rd","4th","5th","6th","7th").map(cell),
    cell[], cell[Place:],
  )
}

// The division/rank grid with the registrant's cell filled.
#let division_table(mark) = {
  let filled(r, c) = mark != none and mark.row == r and mark.col == c
  let body_cells = ()
  for (ri, row) in div_rows.enumerate() {
    body_cells.push(table.cell(align: left)[#row.at(0)])
    body_cells.push(table.cell(align: center)[#row.at(1)])
    for ci in range(7) {
      let on = filled(ri, ci)
      let content = if on and ci == 0 and mark.degree != none {
        text(fill: white, weight: "bold")[#mark.degree]
      } else { [] }
      body_cells.push(table.cell(fill: if on { black } else { none })[#content])
    }
  }
  table(
    columns: (auto, auto) + (1fr,) * 7,
    inset: 3pt, align: horizon + center, stroke: 0.6pt + black,
    table.header(
      table.cell(align: left)[*DIVISION*], [*Age Group*],
      ..belt_headers.map(h => table.cell(text(size: 7pt)[*#h*])),
    ),
    ..body_cells,
  )
}

// One registration card. `c` is a dict, or `none` for a blank card.
#let card(c) = {
  let g(key) = if c == none { "" } else { c.at(key, default: "") }
  block(width: 100%, height: 4.9in, breakable: false)[
    #place_bar()
    #align(center)[#text(size: 12pt)[-- DO NOT WRITE ABOVE THIS LINE --]]
    #v(2pt)

    // Header: logo + event (+ note) on the left, school/instructor box on the right.
    #grid(
      columns: (1.35fr, 1fr), column-gutter: 6pt,
      [
        #grid(columns: (auto, 1fr), column-gutter: 6pt, align: horizon,
          image(logo_path, width: 1.35in),
          [
            #align(center)[#text(size: 15pt, weight: "bold")[#doc.event]]
            #if c != none and g("has_notes") == true [
              #v(3pt)
              #align(center)[#text(size: 13pt, fill: red, weight: "bold")[\*\* NOTES \*\*]]
            ]
          ],
        )
      ],
      box(fill: luma(240), stroke: 0.6pt + black, inset: 5pt, width: 100%)[
        #strong[School or Branch:] \
        #text(size: 10pt)[ #g("school")#if c != none and g("city") != "" [ (#g("city"), #g("state"))]] \
        #v(2pt)
        #strong[Instructor(s):] \
        #text(size: 10pt)[ #g("instructors")] \
        #v(2pt)
        #strong[Instructor Rank(s):] \
        #text(size: 10pt)[ #g("instructor_ranks")]
      ],
    )
    #v(4pt)

    // Personal fields. DOB carries the calculated age in parens; the freed-up
    // Age column widens the Name line.
    #let dob_age = if g("dob") != "" and g("age") != "" {
      g("dob") + " (" + g("age") + ")"
    } else { g("dob") }
    #grid(columns: (3.4fr, 1.5fr, 0.8fr, 0.9fr, 0.9fr), column-gutter: 5pt,
      field("Name (Print):", g("name")),
      field("DOB:", dob_age, value-size: 9pt),
      field("Sex:", g("sex")),
      field("Wt.:", g("weight")),
      field("Ht.:", g("height")),
    )
    #v(4pt)
    #field("Home Address:", g("address"), value-size: 10pt)
    #v(4pt)
    #grid(columns: (2fr, 1fr), column-gutter: 8pt,
      field("Email Address:", g("email"), value-size: 10pt),
      field("Phone:", g("phone"), value-size: 10pt),
    )
    #v(4pt)
    #grid(columns: (1fr, 2fr), column-gutter: 8pt,
      field("Date:", ""), field("Signature:", ""),
    )
    #v(4pt)
    #grid(columns: (auto, 1fr), column-gutter: 6pt, align: bottom,
      strong[IF UNDER LEGAL AGE PARENT OR GUARDIAN MUST CO-SIGN:],
      box(width: 100%, stroke: (bottom: 0.6pt + black), inset: (bottom: 1.5pt))[ ],
    )
    #v(5pt)
    #division_table(if c == none { none } else { c.at("mark", default: none) })
  ]
}

// A full page of the notes for a set of registrants.
//
// The card carries only a flag. A long note used to push the fixed-height card
// block past its bottom edge and collide the division grid with itself, and
// there is no length a note can be trimmed to that is both safe for the layout
// and still useful to a ring table. Flows onto further pages when it has to.
#let notes_page(entries, heading) = {
  align(center)[
    #text(size: 20pt, weight: "bold", fill: red)[\*\* NOTES \*\*]
    #v(3pt)
    #text(size: 14pt)[#heading]
  ]
  v(12pt)
  for e in entries {
    block(breakable: false, width: 100%, below: 10pt)[
      #text(size: 13pt, weight: "bold")[#e.name]
      #if e.school != "" [ #h(6pt) #text(size: 10pt, fill: luma(90))[#e.school] ]
      #v(4pt)
      #for item in e.items [
        #grid(
          columns: (0.95in, 1fr), column-gutter: 6pt, align: top,
          text(size: 8.5pt, weight: "bold",
               fill: if item.scope == "permanent" { red } else { rgb("#b26a00") })[
            #if item.scope == "permanent" [PERMANENT] else [THIS EVENT]
          ],
          text(size: 11pt)[#item.text],
        )
        #v(3pt)
      ]
    ]
  }
}

// A dashed cut guide between the two half-sheets.
#let cut_guide() = { v(1fr); line(length: 100%, stroke: (paint: luma(150), dash: "dashed")); v(1fr) }

// An empty half-sheet — used for the odd trailing slot so it reads as blank
// white space rather than an (unfilled) card.
#let blank_slot() = block(width: 100%, height: 4.9in, breakable: false)[]

// Full-page division cover / divider, so each division prints as a complete
// stack: cut the card sheets and drop the whole set in an envelope.
#let full_cover(div) = {
  v(1fr)
  align(center)[
    #image(logo_path, width: 3in)
    #v(1em)
    #text(size: 42pt, weight: "bold")[#div.label]
    #v(0.5em)
    #text(size: 22pt)[#doc.event]
    #v(1em)
    #text(size: 18pt, fill: luma(80))[#div.cards.len() competitor#if div.cards.len() != 1 [s]]
  ]
  v(1fr)
}

#if "divisions" in doc {
  // Grouped by division: an optional full-page cover, then the division's cards
  // 2-up. Page-broken so each division is a complete stack.
  let first = true
  for div in doc.divisions {
    if doc.at("covers", default: false) {
      if not first { pagebreak() }
      first = false
      full_cover(div)
    }
    // Notes ride on top of the division's stack, straight after its cover, so
    // whoever picks the envelope up reads them before working the ring.
    let div_notes = div.at("notes", default: ())
    if div_notes.len() > 0 {
      if not first { pagebreak() }
      first = false
      notes_page(div_notes, div.label)
    }
    let cards = div.cards
    for i in range(0, cards.len(), step: 2) {
      if not first { pagebreak() }
      first = false
      card(cards.at(i))
      cut_guide()
      if i + 1 < cards.len() { card(cards.at(i + 1)) } else { blank_slot() }
    }
  }
} else {
  // Flat: every card 2-up (existing behavior).
  let cards = doc.cards
  for i in range(0, cards.len(), step: 2) {
    card(cards.at(i))
    cut_guide()
    card(if i + 1 < cards.len() { cards.at(i + 1) } else { none })
    if i + 2 < cards.len() { pagebreak() }
  }
  // No separators in a flat print, so the notes go at the back.
  let all_notes = doc.at("notes", default: ())
  if all_notes.len() > 0 {
    if cards.len() > 0 { pagebreak() }
    notes_page(all_notes, doc.event)
  }
}
