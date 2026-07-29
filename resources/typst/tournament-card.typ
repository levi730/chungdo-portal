// Combined-tournament registration card — Forms and/or Sparring "applications".
// A separate layout from registration-card.typ (kept for legacy events): the
// go-forward card for typed competition events (Sparring / Forms / Combined).
// Renders 2-up on US-letter pages (cut in half after printing).
//
// Data is passed as a JSON file path via `--input data=<root-relative path>`:
//   { "event": "2026 St. Louis Gateway Championships",
//     "subtitle": "Chung Do Association — All Members",
//     "host": "Blue Wave Main School", "where": "...", "when": "...",
//     "start_time": "8:00 a.m.", "fee": "$90 (Forms + Sparring)",
//     "cards": [ <card>|null, ... ] }
// A card:
//   { "variant": "forms"|"sparring", "name","age","dob","sex","weight",
//     "height","address","email","phone","school","instructors",
//     "instructor_ranks","note",
//     "mark": { "row": 0-4, "col": 0-7 } | null }

#let doc = json(sys.inputs.data)

#set page(paper: "us-letter", margin: (x: 0.3in, top: 0.25in, bottom: 0.25in))
#set text(font: ("Arial", "Liberation Sans", "DejaVu Sans"), size: 8pt)
// Control vertical rhythm explicitly with #v(); no implicit block spacing.
#set block(spacing: 0pt)
#set par(spacing: 0pt)

#let blue = rgb("#1F4E9B")

// Belt columns (label, header colour). White renders black — can't ink white.
#let belt_headers = (
  ("Black (3)", black), ("Black (2)", black), ("Black (1)", black),
  ("Brown (1,2)", rgb("#7B3F00")), ("Purple (3,4)", rgb("#7030A0")),
  ("Green (5,6)", rgb("#1F8A1F")), ("Yellow (7,8)", rgb("#B08D00")),
  ("White (9,10)", black),
)
#let div_rows = (
  ("Mini Pee Wee", "5 – 8"), ("Pee Wee", "9 – 11"), ("Junior", "12 – 15"),
  ("Open Adults", "16 – 39"), ("Executives", "40+"),
)

// A write-on line: value area on top, small label beneath (matches the paper form).
#let fld(label, value: "", h: 12pt) = block(width: 100%, breakable: false)[
  #box(width: 100%, height: h, stroke: (bottom: 0.6pt + black), inset: (left: 3pt, bottom: 1pt))[
    #align(bottom + left)[#text(size: 10pt)[#value]]
  ]
  #text(size: 6.5pt)[#label]
]

// Top placement bar: 1st–10th boxes, a blank, then a Place cell.
#let place_bar() = {
  let cell(body) = box(width: 100%, height: 14pt, stroke: 0.6pt + black, inset: 2pt)[#text(size: 7pt)[#body]]
  grid(
    columns: (1fr,) * 10 + (1.4fr, 1.4fr),
    ..("1st","2nd","3rd","4th","5th","6th","7th","8th","9th","10th").map(cell),
    cell[], cell(align(right)[#strong[Place]]),
  )
}

// Event/venue info box (top-right of the header). `paid` X-marks the Paid box.
#let info_box(paid) = box(fill: luma(240), stroke: 0.6pt + black, inset: 5pt, width: 100%)[
  #set text(size: 8pt)
  #set par(leading: 4pt)
  #strong[Where:] #doc.at("where", default: "") \
  #strong[Hosted by:] #doc.at("host", default: "") \
  #strong[When:] #doc.at("when", default: "")  #h(4pt) #strong[Start Time:] #doc.at("start_time", default: "") \
  #v(2pt)
  #align(center)[#strong[Spectator Admission is FREE]]
  #v(2pt)
  #strong[Registration:] #doc.at("fee", default: "") #h(6pt) Paid #box(stroke: 0.6pt + black, width: 9pt, height: 9pt, baseline: 1.5pt)[
    #if paid [#align(center + horizon)[#text(size: 8pt, weight: "bold")[X]]]
  ]
]

// Left title stack; the "application" heading colour signals the variant.
#let title_block(variant) = [
  #text(size: 13pt, weight: "bold", fill: blue)[#doc.event] \
  #text(size: 9pt, weight: "bold")[#doc.at("subtitle", default: "")]
  #v(4pt)
  #if variant == "sparring" [
    #text(size: 18pt, weight: "bold", fill: red)[Sparring Application]
  ] else [
    #text(size: 18pt, weight: "bold", fill: blue)[Forms Application]
  ]
]

// One sex label, circled when it's this registrant's sex.
#let sex_mark(label, on) = if on {
  box(stroke: 1.4pt + black, radius: 40%, inset: (x: 8pt, y: 2pt))[#text(size: 12pt, weight: "bold")[#label]]
} else {
  text(size: 12pt, weight: "bold")[#label]
}

// The tan gender banner: combined for forms, MALE/FEMALE circle for sparring.
#let gender_banner(variant, sex) = box(width: 100%, fill: rgb("#FBF3D6"), stroke: 0.6pt + black, inset: 4pt)[
  #align(center)[
    #if variant == "sparring" [
      #grid(columns: (1fr, auto, 1fr), align: horizon, column-gutter: 10pt,
        align(right)[#sex_mark("MALE", sex == "M")],
        text(size: 9pt)[(circle one)],
        align(left)[#sex_mark("FEMALE", sex == "F")],
      )
    ] else [
      #text(size: 12pt, weight: "bold")[Forms are combined Male and Female]
    ]
  ]
]

// The division/rank grid with the registrant's cell filled.
#let division_table(mark) = {
  let filled(r, c) = mark != none and mark.row == r and mark.col == c
  let body = ()
  for (ri, row) in div_rows.enumerate() {
    body.push(table.cell(align: left)[#text(weight: "bold")[#row.at(0)]])
    body.push(table.cell(align: left)[#row.at(1)])
    for ci in range(8) {
      body.push(table.cell(fill: if filled(ri, ci) { black } else { none })[])
    }
  }
  table(
    columns: (auto, auto) + (1fr,) * 8,
    inset: 3pt, align: horizon + center, stroke: 0.6pt + black,
    table.header(
      table.cell(align: left)[*DIVISION*], [*Age Group*],
      ..belt_headers.map(h => table.cell(text(size: 6.5pt, fill: h.at(1))[*#h.at(0)*])),
    ),
    ..body,
  )
}

// One registration card. `c` is a dict, or `none` for a blank card.
#let card(c) = {
  let g(key) = if c == none { "" } else { c.at(key, default: "") }
  let variant = if c == none { "forms" } else { c.at("variant", default: "forms") }
  let mark = if c == none { none } else { c.at("mark", default: none) }
  let paid = if c == none { false } else { c.at("paid", default: false) }
  block(width: 100%, height: 5.15in, breakable: false)[
    #place_bar()
    #v(1pt)
    #text(size: 7pt, style: "italic", fill: red)[Do Not Write Above This Line]
    #v(2pt)

    #grid(columns: (1.35fr, 1fr), column-gutter: 8pt, align: top,
      title_block(variant),
      info_box(paid),
    )
    #v(2pt)

    // Personal fields.
    #grid(columns: (3.4fr, 0.85fr, 1.7fr, 0.85fr, 0.85fr), column-gutter: 6pt,
      fld("Name (Print)", value: g("name")),
      fld("Age", value: g("age")),
      fld("Date of Birth", value: g("dob")),
      fld("Wt.", value: g("weight")),
      fld("Ht.", value: g("height")),
    )
    #v(2pt)
    #fld("Home Address", value: g("address"))
    #v(2pt)
    #grid(columns: (2fr, 1fr), column-gutter: 8pt,
      fld("Email Address", value: g("email")),
      fld("Phone", value: g("phone")),
    )
    #v(2pt)
    #fld("School or Branch", value: g("school"))
    #v(2pt)
    #grid(columns: (1.7fr, 1fr), column-gutter: 8pt,
      fld("Instructor's Name", value: g("instructors")),
      fld("Instructor Rank", value: g("instructor_ranks")),
    )
    #v(2pt)
    #grid(columns: (1fr, 2fr), column-gutter: 8pt,
      fld("Date", value: ""),
      fld("Signature", value: ""),
    )
    #v(2pt)
    #align(center)[
      #text(size: 8pt, weight: "bold")[IF UNDER LEGAL AGE, PARENT OR GUARDIAN MUST CO-SIGN]
      #linebreak()
      #text(size: 7pt)[I agree to the terms on the reverse side of this card]
    ]
    #if c != none and g("note") != "" [
      #v(1pt)
      #align(center)[#text(size: 8pt)[#text(fill: red, weight: "bold")[Note:] #g("note")]]
    ]
    #v(2pt)

    #gender_banner(variant, g("sex"))
    #v(2pt)
    #text(size: 7pt)[Mark your division below by shading in one box]
    #v(1pt)
    #division_table(mark)
  ]
}

// A dashed cut guide between the two half-sheets.
#let cut_guide() = { v(1fr); line(length: 100%, stroke: (paint: luma(150), dash: "dashed")); v(1fr) }

// An empty half-sheet for the odd trailing slot.
#let blank_slot() = block(width: 100%, height: 5.15in, breakable: false)[]

// Full-page division cover / divider.
#let full_cover(div) = {
  let disc = div.at("discipline", default: "")
  v(1fr)
  align(center)[
    #text(size: 42pt, weight: "bold")[#div.label]
    #if disc != "" [
      #v(0.7em)
      #text(size: 24pt, weight: "bold", fill: if disc == "sparring" { red } else { blue })[
        #if disc == "sparring" [Sparring] else [Forms]
      ]
    ]
    #v(0.9em)
    #text(size: 22pt)[#doc.event]
    #v(0.9em)
    #text(size: 18pt, fill: luma(80))[#div.cards.len() competitor#if div.cards.len() != 1 [s]]
  ]
  v(1fr)
}

#if "divisions" in doc {
  let first = true
  for div in doc.divisions {
    if doc.at("covers", default: false) {
      if not first { pagebreak() }
      first = false
      full_cover(div)
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
  let cards = doc.cards
  for i in range(0, cards.len(), step: 2) {
    card(cards.at(i))
    cut_guide()
    card(if i + 1 < cards.len() { cards.at(i + 1) } else { none })
    if i + 2 < cards.len() { pagebreak() }
  }
}
