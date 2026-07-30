// Meal-ticket voucher — one per household, printed by the registrant and
// presented at the meal station to redeem.
//
// Data is passed as a JSON file path via `--input data=<root-relative path>`:
//   { "event","when","where","org","logo","purchaser","reference",
//     "total": <int>, "label": "Meal",
//     "lines": [ { "name": "...", "meals": <int> }, ... ],
//     "menu": "<markdown-ish text>" }

#let doc = json(sys.inputs.data)
#let logo_path = doc.at("logo", default: "/public/img/CDKTKD_logo.svg")

#set page(paper: "us-letter", margin: 0.75in)
#set text(font: ("Arial", "Liberation Sans", "DejaVu Sans"), size: 11pt)

// Render the menu string: "# heading", "- "/"* " bullets, else a paragraph.
#let render_menu(menu) = {
  for raw in menu.split("\n") {
    let line = raw.trim()
    if line == "" { continue }
    if line.starts-with("#") {
      block(above: 8pt, below: 4pt)[
        #text(weight: "bold", size: 12pt)[#line.replace(regex("^#+\s*"), "")]
      ]
    } else if line.starts-with("- ") or line.starts-with("* ") {
      block(below: 3pt)[#pad(left: 10pt)[• #line.slice(2)]]
    } else {
      block(below: 3pt)[#line]
    }
  }
}

// --- Header --------------------------------------------------------------
#align(center)[
  #image(logo_path, width: 1.6in)
  #v(4pt)
  #text(size: 13pt, weight: "bold")[#doc.org]
]
#v(8pt)
#align(center)[
  #text(size: 24pt, weight: "bold")[MEAL TICKET]
  #v(2pt)
  #text(size: 15pt)[#doc.event]
  #if doc.when != "" [
    #linebreak()
    #text(size: 11pt, fill: luma(90))[#doc.when#if doc.where != "" [ · #doc.where]]
  ]
]
#v(14pt)

// --- Redemption box ------------------------------------------------------
#block(width: 100%, stroke: 1.2pt + black, radius: 6pt, inset: 16pt)[
  #align(center)[
    #text(size: 15pt)[Admits] #h(6pt)
    #text(size: 34pt, weight: "bold")[#doc.total] #h(6pt)
    #text(size: 15pt)[\u{00D7} #doc.label]
  ]
  #v(8pt)
  #line(length: 100%, stroke: 0.5pt + luma(180))
  #v(8pt)
  #grid(
    columns: (1fr, auto), column-gutter: 8pt, align: (left, right),
    [*Purchaser:* #doc.purchaser],
    [*Ref:* #raw(doc.reference)],
  )
  #if doc.lines.len() > 1 [
    #v(8pt)
    #text(size: 9.5pt, fill: luma(90))[
      #for l in doc.lines [ #l.name — #l.meals \ ]
    ]
  ]
]
#v(16pt)

// --- Menu ----------------------------------------------------------------
#if doc.menu != "" [
  #text(size: 14pt, weight: "bold")[Menu]
  #v(2pt)
  #line(length: 100%, stroke: 0.6pt + black)
  #v(6pt)
  #render_menu(doc.menu)
]

// --- Footer --------------------------------------------------------------
#v(1fr)
#align(center)[
  #text(size: 9pt, fill: luma(110))[
    Present this ticket to redeem. Valid only for
    #doc.event#if doc.when != "" [ on #doc.when]#if doc.where != "" [ at #doc.where].
  ]
]
