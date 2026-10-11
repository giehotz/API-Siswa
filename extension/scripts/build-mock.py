import json

with open(r'd:\ext_emis_dl_sis\emis_kemenag_go_id.har', 'r', encoding='utf-8') as f:
    har = json.load(f)

raw = json.loads(har['log']['entries'][9]['response']['content']['text'])['results']

# Anonymize PII
raw['full_name'] = 'AHMAD FAUZI PRATAMA'
raw['family_head_name'] = 'BAMBANG PRATAMA'
raw['nik'] = '3201010105150001'
raw['nisn'] = '0151234567'
raw['local_nis'] = '20261011002345'
raw['kk_num'] = '3201010101010002'
raw['birth_place'] = 'TANGGAMUS'
raw['birth_date'] = '2015-05-12'
raw['address'] = 'JL. RAYA KESANTRIAN NO. 45'
raw['full_address'] = 'JL. RAYA KESANTRIAN NO. 45 RT 002 RW 001 KUTA DALOM GISTING TANGGAMUS LAMPUNG'
raw['rt'] = '002'
raw['rw'] = '001'
raw['postal_code_num'] = 35378

# Parents
if 'parents' in raw and isinstance(raw['parents'], dict):
    p = raw['parents']
    p['father_full_name'] = 'BAMBANG PRATAMA'
    p['father_nik'] = '3201010101750001'
    p['father_address'] = 'JL. RAYA KESANTRIAN NO. 45'
    p['father_rt'] = '002'
    p['father_rw'] = '001'
    p['father_phone_number'] = '081234567890'
    p['mother_full_name'] = 'SRI WAHYUNI'
    p['mother_nik'] = '3201010101800002'
    p['mother_address'] = 'JL. RAYA KESANTRIAN NO. 45'
    p['mother_rt'] = '002'
    p['mother_rw'] = '001'
    p['mother_phone_number'] = '081298765432'
    p['wali_full_name'] = None
    p['wali_nik'] = None

js_content = """/**
 * Mock sample student data derived from emis_kemenag_go_id.har (Anonymized)
 */
const MOCK_STUDENT_RESULTS = """ + json.dumps(raw, indent=2, ensure_ascii=False) + """;

if (typeof module !== "undefined" && module.exports) {
  module.exports = { MOCK_STUDENT_RESULTS };
}
"""

with open(r'd:\ext_emis_dl_sis\scripts\mock-sample.js', 'w', encoding='utf-8') as f:
    f.write(js_content)

print("Created d:\\ext_emis_dl_sis\\scripts\\mock-sample.js successfully.")
