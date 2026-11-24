import re, subprocess, json

STATES = {"AL":"Alabama","AK":"Alaska","AZ":"Arizona","AR":"Arkansas","CA":"California","CO":"Colorado","CT":"Connecticut","DE":"Delaware","FL":"Florida","GA":"Georgia","HI":"Hawaii","ID":"Idaho","IL":"Illinois","IN":"Indiana","IA":"Iowa","KS":"Kansas","KY":"Kentucky","LA":"Louisiana","ME":"Maine","MD":"Maryland","MA":"Massachusetts","MI":"Michigan","MN":"Minnesota","MS":"Mississippi","MO":"Missouri","MT":"Montana","NE":"Nebraska","NV":"Nevada","NH":"New Hampshire","NJ":"New Jersey","NM":"New Mexico","NY":"New York","NC":"North Carolina","ND":"North Dakota","OH":"Ohio","OK":"Oklahoma","OR":"Oregon","PA":"Pennsylvania","RI":"Rhode Island","SC":"South Carolina","SD":"South Dakota","TN":"Tennessee","TX":"Texas","UT":"Utah","VT":"Vermont","VA":"Virginia","WA":"Washington","WV":"West Virginia","WI":"Wisconsin","WY":"Wyoming"}

# parse themes (section 4) and students (section 3) from the DS strategy doc
text = open('docs/reference/ds_assignments_and_themes.md', encoding='utf-8').read()
themes = {}
for m in re.finditer(r'^\|\s*(\d+)\s*\|\s*([^|]+?)\s*\|\s*([^|]+?)\s*\|\s*([^|]+?)\s*\|\s*$', text, re.M):
    n, a, b, c = m.groups()
    # section-4 rows have two person names in cols 3-4; section-3 rows have 7 columns so won't match this 4-col regex
    themes[int(n)] = a
students = []
for m in re.finditer(r'^\|\s*\d+\s*\|\s*([^|]+?)\s*\|\s*([^|]+?)\s*\|\s*([A-Z]{2})\s*\|\s*([^|]+?)\s*\|\s*([^|]+?)\s*\|\s*([^|]+?)\s*\|\s*$', text, re.M):
    name, state, abbr, srange, theme, nrange = m.groups()
    first, last = name.rsplit(' ', 1)
    students.append((last, first, abbr, srange.replace('–','-'), theme, nrange.replace('–','-')))
assert len(themes) == 29, len(themes)
assert len(students) == 58, len(students)
theme_id = {v:k for k,v in themes.items()}
for s in students:
    assert s[4] in theme_id, s

def q(s): return "'" + s.replace("\\","\\\\").replace("'","\\'") + "'"

with open('sql/003_seed_assignments.sql','w',encoding='utf-8') as f:
    f.write("-- Generated from scholarships-data-collection-strategy.md (Data Science class). 58 students.\n")
    f.write("-- Re-generate with tools/gen_seed.py if the assignment sheet changes.\nSET NAMES utf8mb4;\n")
    f.write("INSERT INTO student_assignments (last_name, first_name, state_code, state_range, national_theme_id, national_range) VALUES\n")
    rows = [f"({q(l)}, {q(fn)}, {q(st)}, {q(sr)}, {theme_id[th]}, {q(nr)})" for l,fn,st,sr,th,nr in students]
    f.write(",\n".join(rows) + ";\n")

with open('sql/002_seed_reference.sql','w',encoding='utf-8') as f:
    f.write("-- Reference data. Categories and fields of study are a PROPOSED starting list:\n")
    f.write("-- compare with what the Data Science files actually contain and adjust (FR-SA-04).\nSET NAMES utf8mb4;\n\n")
    f.write("INSERT INTO roles (id, name) VALUES (1,'student'),(2,'parent'),(3,'administrator'),(4,'super_admin');\n\n")
    f.write("-- Placeholder development user (Iterations 2-6). Inactive, so nobody can log in as it.\n")
    f.write("-- Reassign its records to a real administrator in Iteration 7.\n")
    f.write("INSERT INTO users (id, role_id, email, password_hash, display_name, is_active) VALUES (1, 4, 'dev-placeholder@localhost.invalid', '!', 'Development user', 0);\n\n")
    f.write("INSERT INTO states (code, name) VALUES ('US','National'),\n")
    f.write(",\n".join(f"({q(c)}, {q(n)})" for c,n in STATES.items()) + ";\n\n")
    f.write("INSERT INTO grade_levels (grade, label, school_level) VALUES\n")
    f.write(",\n".join(f"({g}, 'Grade {g}', '{'middle' if g<=8 else 'high'}')" for g in range(6,13)) + ";\n\n")
    ptypes = ["Foundation/nonprofit","Corporation","College/university","Government agency","Professional association","Community organization","Other"]
    f.write("INSERT INTO provider_types (name) VALUES " + ",".join(f"({q(p)})" for p in ptypes) + ";\n\n")
    cats = ["Merit","Need-based","Community service","Leadership","STEM","Arts","Athletics","Essay/contest","Heritage or identity","Career and technical","Military-connected","First-generation","Disability-related","Faith-based","Other"]
    f.write("INSERT INTO categories (name) VALUES " + ",".join(f"({q(c)})" for c in cats) + ";\n\n")
    fields = ["Any / undecided","Agriculture and environment","Arts, music, and design","Business and finance","Computer science and IT","Education and teaching","Engineering","Health and medicine","Humanities and social sciences","Law, public service, and government","Math","Science (general)","Skilled trades and technical","Writing, journalism, and communications"]
    f.write("INSERT INTO fields_of_study (name) VALUES " + ",".join(f"({q(x)})" for x in fields) + ";\n\n")
    f.write("-- The 29 national themes, numbered as in the Data Science assignment sheet.\n")
    f.write("INSERT INTO national_themes (id, name) VALUES\n")
    f.write(",\n".join(f"({k}, {q(v)})" for k,v in sorted(themes.items())) + ";\n")
print("themes", len(themes), "students", len(students))
