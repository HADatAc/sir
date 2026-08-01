["person_uri","full_name","organization","organization_uri"],
(.body[] | [
  (.uri // ""),
  (((.givenName // "") + " " + (.familyName // "") | gsub("^ +| +$";"")) as $n | if $n == "" then (.label // "") else $n end),
  (.hasAffiliation.label // ""),
  (.hasAffiliation.uri // "")
])
| @csv
