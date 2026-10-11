/**
 * normalizer.js - EMIS Student Data Normalizer
 * Transforms raw EMIS student API results (104 fields) into structured tabular sheets.
 * Follows PRD v1.1 specs, privacy controls, and formula injection defense.
 */

(function (root, factory) {
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = factory();
  } else {
    root.EmisNormalizer = factory();
  }
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';

  /**
   * Sanitize text against spreadsheet formula injection (=, +, -, @)
   * Prepend single quote (') so spreadsheet engines treat value as plain text.
   */
  function sanitizeCell(val) {
    if (val === null || val === undefined) return '';
    const str = String(val).trim();
    if (str.length === 0) return '';
    if (str.startsWith('=') || str.startsWith('+') || str.startsWith('-') || str.startsWith('@')) {
      return "'" + str;
    }
    return str;
  }

  /**
   * Safely format an ID or numeric string to string without losing zeros
   */
  function strVal(val) {
    if (val === null || val === undefined) return '';
    return sanitizeCell(String(val).trim());
  }

  /**
   * Extract label from embedded object or fallback
   */
  function getLabel(obj, fallbackKey) {
    if (!obj) return '';
    if (typeof obj === 'string') return sanitizeCell(obj);
    if (typeof obj === 'object') {
      if (obj.name) return sanitizeCell(obj.name);
      if (obj.label) return sanitizeCell(obj.label);
      if (fallbackKey && obj[fallbackKey]) return sanitizeCell(obj[fallbackKey]);
    }
    return '';
  }

  /**
   * Normalize an array of student objects into multi-sheet tables
   * @param {Array<Object>} studentsList Raw student result objects
   * @param {Object} options Configuration options
   * @param {boolean} options.includeSensitive Master toggle for sensitive fields
   * @param {Object} options.sensitiveOptions Granular privacy flags
   * @returns {Object} Tables per sheet
   */
  function normalizeStudents(studentsList, options = {}) {
    const opts = {
      includeSensitive: false,
      sensitiveOptions: {
        nik: false,
        kk: false,
        parentsNik: false,
        bansos: false,
        coordinates: false,
        ...options.sensitiveOptions
      },
      ...options
    };

    const allowNik = opts.includeSensitive && opts.sensitiveOptions.nik;
    const allowKk = opts.includeSensitive && opts.sensitiveOptions.kk;
    const allowParentsNik = opts.includeSensitive && opts.sensitiveOptions.parentsNik;
    const allowBansos = opts.includeSensitive && opts.sensitiveOptions.bansos;
    const allowCoords = opts.includeSensitive && opts.sensitiveOptions.coordinates;

    const dataSiswaRows = [];
    const dataOrangTuaRows = [];
    const dataPembelajaranRows = [];
    const dataVaksinRows = [];
    const dataBantuanRows = [];
    const dataPrestasiRows = [];
    const dataKebutuhanKhususRows = [];
    const dataBeasiswaRows = [];

    const seenIds = new Set();
    let duplicateCount = 0;

    const safeStudents = Array.isArray(studentsList) ? studentsList : [studentsList].filter(Boolean);

    safeStudents.forEach((student, index) => {
      if (!student) return;

      // Deduplication based on integration_id or siswa_id or id
      const dedupKey = student.integration_id || student.siswa_id || student.id || student.nisn || `idx_${index}`;
      if (seenIds.has(dedupKey)) {
        duplicateCount++;
        return;
      }
      seenIds.add(dedupKey);

      const nisn = strVal(student.nisn);
      const nama = sanitizeCell(student.full_name);

      // --- 1. DATA SISWA ---
      const rowSiswa = {
        'No': dataSiswaRows.length + 1,
        'NISN': nisn,
        'NIS Lokal': strVal(student.local_nis),
        'Nama Lengkap': nama,
        'Jenis Kelamin': getLabel(student.m_gender),
        'Tempat Lahir': sanitizeCell(student.birth_place),
        'Tanggal Lahir': sanitizeCell(student.birth_date),
        'Agama': getLabel(student.m_religion),
        'Kewarganegaraan': sanitizeCell(student.nationality || 'WNI'),
        'Anak Ke': student.child_of_num !== null && student.child_of_num !== undefined ? student.child_of_num : '',
        'Jumlah Saudara': student.siblings_num !== null && student.siblings_num !== undefined ? student.siblings_num : '',
        'Alamat Lengkap': sanitizeCell(student.full_address || student.address),
        'RT': strVal(student.rt),
        'RW': strVal(student.rw),
        'Kelurahan / Desa': getLabel(student.m_sub_district),
        'Kecamatan': getLabel(student.m_district),
        'Kabupaten / Kota': getLabel(student.m_city),
        'Provinsi': getLabel(student.m_province),
        'Kode Pos': student.postal_code_num !== null && student.postal_code_num !== undefined ? student.postal_code_num : '',
        'Status Tempat Tinggal': getLabel(student.m_residence_status),
        'Jarak ke Madrasah': getLabel(student.m_residence_distance),
        'Waktu Tempuh': getLabel(student.m_interval_time),
        'Transportasi': getLabel(student.m_transportation),
        'No Handphone': strVal(student.handphone),
        'Email': sanitizeCell(student.email),
        'Cita-cita': getLabel(student.m_life_goal),
        'Hobi': getLabel(student.m_hobby)
      };

      // Sensitive fields in Data Siswa
      if (allowNik) {
        rowSiswa['NIK Siswa'] = strVal(student.nik);
      }
      if (allowKk) {
        rowSiswa['No Kartu Keluarga'] = strVal(student.kk_num);
        rowSiswa['Nama Kepala Keluarga'] = sanitizeCell(student.family_head_name);
      }
      if (allowCoords) {
        rowSiswa['Latitude'] = student.latitude !== null && student.latitude !== undefined ? student.latitude : '';
        rowSiswa['Longitude'] = student.longitude !== null && student.longitude !== undefined ? student.longitude : '';
      }

      dataSiswaRows.push(rowSiswa);

      // --- 2. DATA ORANG TUA ---
      const parents = student.parents || {};
      const rowOrangTua = {
        'No': dataOrangTuaRows.length + 1,
        'NISN': nisn,
        'Nama Siswa': nama,

        // Data Ayah
        'Nama Ayah': sanitizeCell(parents.father_full_name),
        'Status Ayah': getLabel(parents.father_life_status),
        'Pendidikan Ayah': getLabel(parents.father_last_education || parents.father_latest_education),
        'Pekerjaan Ayah': getLabel(parents.father_job || parents.father_m_job),
        'Penghasilan Ayah': getLabel(parents.father_average_income_per_month),
        'No HP Ayah': strVal(parents.father_phone_number),
        'Alamat Ayah': sanitizeCell(parents.father_address),
        'RT Ayah': strVal(parents.father_rt),
        'RW Ayah': strVal(parents.father_rw),
        'Kelurahan Ayah': getLabel(parents.father_sub_district),
        'Kecamatan Ayah': getLabel(parents.father_district),
        'Kota Ayah': getLabel(parents.father_city),
        'Provinsi Ayah': getLabel(parents.father_province),
        'Kode Pos Ayah': strVal(parents.father_postal_code),

        // Data Ibu
        'Nama Ibu': sanitizeCell(parents.mother_full_name),
        'Status Ibu': getLabel(parents.mother_life_status),
        'Pendidikan Ibu': getLabel(parents.mother_last_education || parents.mother_latest_education),
        'Pekerjaan Ibu': getLabel(parents.mother_job || parents.mother_m_job),
        'Penghasilan Ibu': getLabel(parents.mother_average_income_per_month),
        'No HP Ibu': strVal(parents.mother_phone_number),
        'Alamat Ibu': sanitizeCell(parents.mother_address),
        'RT Ibu': strVal(parents.mother_rt),
        'RW Ibu': strVal(parents.mother_rw),
        'Kelurahan Ibu': getLabel(parents.mother_sub_district),
        'Kecamatan Ibu': getLabel(parents.mother_district),
        'Kota Ibu': getLabel(parents.mother_city),
        'Provinsi Ibu': getLabel(parents.mother_province),
        'Kode Pos Ibu': strVal(parents.mother_postal_code),

        // Data Wali
        'Memiliki Wali': parents.wali ? 'Ya' : (parents.wali_full_name ? 'Ya' : 'Tidak'),
        'Nama Wali': sanitizeCell(parents.wali_full_name),
        'Status Wali': getLabel(parents.wali_life_status),
        'Pendidikan Wali': getLabel(parents.wali_last_education),
        'Pekerjaan Wali': getLabel(parents.wali_job),
        'Penghasilan Wali': getLabel(parents.wali_average_income_per_month),
        'No HP Wali': strVal(parents.wali_phone_number),
        'Alamat Wali': sanitizeCell(parents.wali_address)
      };

      if (allowParentsNik) {
        rowOrangTua['NIK Ayah'] = strVal(parents.father_nik);
        rowOrangTua['NIK Ibu'] = strVal(parents.mother_nik);
        rowOrangTua['NIK Wali'] = strVal(parents.wali_nik);
      }

      dataOrangTuaRows.push(rowOrangTua);

      // --- 3. DATA PEMBELAJARAN ---
      const la = student.learning_activity || {};
      const sg = la.study_group || {};
      const ay = la.academic_year || {};
      const ss = la.student_status || {};
      const sd = la.status_description || {};

      dataPembelajaranRows.push({
        'No': dataPembelajaranRows.length + 1,
        'NISN': nisn,
        'Nama Siswa': nama,
        'Tahun Ajaran': sanitizeCell(ay.name || ay.year),
        'Semester': ay.semester !== null && ay.semester !== undefined ? ay.semester : '',
        'Tingkat / Kelas': sg.level !== null && sg.level !== undefined ? sg.level : (la.m_level_id || ''),
        'Rombongan Belajar (Rombel)': sanitizeCell(sg.name),
        'Nomor Absen': la.absent_number !== null && la.absent_number !== undefined ? la.absent_number : '',
        'Wali Kelas': sanitizeCell(sg.teacher_name),
        'Ruang Kelas': sanitizeCell(sg.room_name),
        'Status Siswa': sanitizeCell(ss.name),
        'Keterangan Status': sanitizeCell(sd.name),
        'Tanggal Masuk': sanitizeCell(la.admission_date || student.admission_date),
        'Tanggal Mulai': sanitizeCell(la.start_date)
      });

      // --- 4. DATA VAKSIN ---
      // Check 6 standard vaccines
      const vaccineTypes = Array.isArray(student.student_vaccine_types) ? student.student_vaccine_types : [];
      const vaccineIds = (student.vaccine_type_ids ? String(student.vaccine_type_ids).split(',') : [])
        .map(x => parseInt(x.trim(), 10))
        .filter(x => !isNaN(x));

      function hasVaccine(id, nameSnippet) {
        if (vaccineIds.includes(id)) return true;
        return vaccineTypes.some(vt => {
          if (vt.m_vaccine_type_id === id) return true;
          const vtName = (vt.m_vaccine_type && vt.m_vaccine_type.name) ? vt.m_vaccine_type.name.toLowerCase() : '';
          return vtName.includes(nameSnippet.toLowerCase());
        });
      }

      dataVaksinRows.push({
        'No': dataVaksinRows.length + 1,
        'NISN': nisn,
        'Nama Siswa': nama,
        'Hepatitis B': hasVaccine(1, 'hepatitis') ? 'Ya' : 'Tidak',
        'Polio': hasVaccine(2, 'polio') ? 'Ya' : 'Tidak',
        'BCG': hasVaccine(3, 'bcg') ? 'Ya' : 'Tidak',
        'Campak': hasVaccine(4, 'campak') ? 'Ya' : 'Tidak',
        'DPT': hasVaccine(5, 'dpt') ? 'Ya' : 'Tidak',
        'Covid-19': hasVaccine(6, 'covid') ? 'Ya' : 'Tidak'
      });

      // --- 5. DATA BANTUAN ---
      const rowBantuan = {
        'No': dataBantuanRows.length + 1,
        'NISN': nisn,
        'Nama Siswa': nama,
        'Sumber Dana Pendidikan': getLabel(student.m_fund_source),
        'Nomor KIP': strVal(student.nomor_kip),
        'KIP Tersinkronisasi': student.is_kip_sync ? 'Ya' : 'Tidak'
      };

      if (allowBansos) {
        rowBantuan['Penerima PKH'] = student.pkh_family ? 'Ya' : 'Tidak';
        rowBantuan['Penerima BPNT'] = student.bpnt_family ? 'Ya' : 'Tidak';
        rowBantuan['Anggota PBI'] = student.pbi_member ? 'Ya' : 'Tidak';
        rowBantuan['DTKS Aktif'] = student.dtks_is_active ? 'Ya' : 'Tidak';
        rowBantuan['PKH Aktif'] = student.pkh_is_active ? 'Ya' : 'Tidak';
      }

      dataBantuanRows.push(rowBantuan);

      // --- OPTIONAL DYNAMIC: ACHIEVEMENTS ---
      if (Array.isArray(student.achievements) && student.achievements.length > 0) {
        student.achievements.forEach((ach, aIdx) => {
          dataPrestasiRows.push({
            'No': dataPrestasiRows.length + 1,
            'NISN': nisn,
            'Nama Siswa': nama,
            'Nama Prestasi': sanitizeCell(ach.name || ach.title || ach.achievement_name),
            'Tingkat': sanitizeCell(ach.level || ach.m_level?.name),
            'Tahun': sanitizeCell(ach.year),
            'Peringkat': sanitizeCell(ach.rank)
          });
        });
      }

      // --- OPTIONAL DYNAMIC: DISABILITIES ---
      if (Array.isArray(student.student_disabilities) && student.student_disabilities.length > 0) {
        student.student_disabilities.forEach((dis) => {
          dataKebutuhanKhususRows.push({
            'No': dataKebutuhanKhususRows.length + 1,
            'NISN': nisn,
            'Nama Siswa': nama,
            'Jenis Kebutuhan Khusus': sanitizeCell(dis.name || dis.m_disability?.name)
          });
        });
      }

      // --- OPTIONAL DYNAMIC: SCHOLARSHIPS ---
      if (Array.isArray(student.scholarships) && student.scholarships.length > 0) {
        student.scholarships.forEach((sch) => {
          dataBeasiswaRows.push({
            'No': dataBeasiswaRows.length + 1,
            'NISN': nisn,
            'Nama Siswa': nama,
            'Nama Beasiswa': sanitizeCell(sch.name || sch.scholarship_name),
            'Tahun': sanitizeCell(sch.year),
            'Keterangan': sanitizeCell(sch.description)
          });
        });
      }
    });

    // --- 6. LOG EKSPOR ---
    const now = new Date();
    const logRows = [
      { 'Parameter': 'Waktu Ekspor', 'Nilai': now.toLocaleString('id-ID') },
      { 'Parameter': 'Total Siswa Diproses', 'Nilai': safeStudents.length },
      { 'Parameter': 'Total Berhasil', 'Nilai': dataSiswaRows.length },
      { 'Parameter': 'Total Duplikat Dieliminasi', 'Nilai': duplicateCount },
      { 'Parameter': 'Sertakan Data Sensitif', 'Nilai': opts.includeSensitive ? 'Ya' : 'Tidak' },
      { 'Parameter': 'Status Ekspor', 'Nilai': dataSiswaRows.length > 0 ? 'SUKSES' : 'KOSONG' }
    ];

    const sheets = {
      'Data_Siswa': dataSiswaRows,
      'Data_Orang_Tua': dataOrangTuaRows,
      'Data_Pembelajaran': dataPembelajaranRows,
      'Data_Vaksin': dataVaksinRows,
      'Data_Bantuan': dataBantuanRows,
      'Log_Ekspor': logRows
    };

    if (dataPrestasiRows.length > 0) {
      sheets['Data_Prestasi'] = dataPrestasiRows;
    }
    if (dataKebutuhanKhususRows.length > 0) {
      sheets['Data_Kebutuhan_Khusus'] = dataKebutuhanKhususRows;
    }
    if (dataBeasiswaRows.length > 0) {
      sheets['Data_Beasiswa'] = dataBeasiswaRows;
    }

    return {
      sheets,
      stats: {
        totalFound: safeStudents.length,
        exportedCount: dataSiswaRows.length,
        duplicateCount
      }
    };
  }

  /**
   * Normalize an array of student objects into ONE SINGLE SHEET (all data in 1 row per student)
   * Includes NISN, Local NIS, identity, address, rombel, vaccine, parents, and aid.
   */
  function normalizeStudentsSingleSheet(studentsList, options = {}) {
    const opts = {
      includeSensitive: false,
      sensitiveOptions: {
        nik: false,
        kk: false,
        parentsNik: false,
        bansos: false,
        coordinates: false,
        ...options.sensitiveOptions
      },
      ...options
    };

    const allowNik = opts.includeSensitive && opts.sensitiveOptions.nik;
    const allowKk = opts.includeSensitive && opts.sensitiveOptions.kk;
    const allowParentsNik = opts.includeSensitive && opts.sensitiveOptions.parentsNik;
    const allowBansos = opts.includeSensitive && opts.sensitiveOptions.bansos;
    const allowCoords = opts.includeSensitive && opts.sensitiveOptions.coordinates;

    const rows = [];
    const seenIds = new Set();
    let duplicateCount = 0;

    const safeStudents = Array.isArray(studentsList) ? studentsList : [studentsList].filter(Boolean);

    safeStudents.forEach((student, index) => {
      if (!student) return;

      const dedupKey = student.integration_id || student.siswa_id || student.id || student.nisn || `idx_${index}`;
      if (seenIds.has(dedupKey)) {
        duplicateCount++;
        return;
      }
      seenIds.add(dedupKey);

      const nisn = strVal(student.nisn);
      const nama = sanitizeCell(student.full_name);
      const localNis = strVal(student.local_nis);

      // Learning Activity
      const la = student.learning_activity || {};
      const sg = la.study_group || {};
      const ay = la.academic_year || {};
      const ss = la.student_status || {};
      const sd = la.status_description || {};

      // Vaccines
      const vaccineTypes = Array.isArray(student.student_vaccine_types) ? student.student_vaccine_types : [];
      const vaccineIds = (student.vaccine_type_ids ? String(student.vaccine_type_ids).split(',') : [])
        .map(x => parseInt(x.trim(), 10))
        .filter(x => !isNaN(x));

      function hasVaccine(id, nameSnippet) {
        if (vaccineIds.includes(id)) return true;
        return vaccineTypes.some(vt => {
          if (vt.m_vaccine_type_id === id) return true;
          const vtName = (vt.m_vaccine_type && vt.m_vaccine_type.name) ? vt.m_vaccine_type.name.toLowerCase() : '';
          return vtName.includes(nameSnippet.toLowerCase());
        });
      }

      // Parents
      const parents = student.parents || {};

      const row = {
        'No': rows.length + 1,
        'NISN': nisn,
        'NIS Lokal': localNis,
        'Nama Lengkap': nama,
        'Jenis Kelamin': getLabel(student.m_gender),
        'Tempat Lahir': sanitizeCell(student.birth_place),
        'Tanggal Lahir': sanitizeCell(student.birth_date),
        'Agama': getLabel(student.m_religion),
        'Kewarganegaraan': sanitizeCell(student.nationality || 'WNI'),
        'Anak Ke': student.child_of_num !== null && student.child_of_num !== undefined ? student.child_of_num : '',
        'Jumlah Saudara': student.siblings_num !== null && student.siblings_num !== undefined ? student.siblings_num : '',
        
        // Alamat Siswa
        'Alamat Lengkap': sanitizeCell(student.full_address || student.address),
        'RT': strVal(student.rt),
        'RW': strVal(student.rw),
        'Kelurahan / Desa': getLabel(student.m_sub_district),
        'Kecamatan': getLabel(student.m_district),
        'Kabupaten / Kota': getLabel(student.m_city),
        'Provinsi': getLabel(student.m_province),
        'Kode Pos': student.postal_code_num !== null && student.postal_code_num !== undefined ? student.postal_code_num : '',
        'Status Tempat Tinggal': getLabel(student.m_residence_status),
        'Jarak ke Madrasah': getLabel(student.m_residence_distance),
        'Waktu Tempuh': getLabel(student.m_interval_time),
        'Transportasi': getLabel(student.m_transportation),
        'No Handphone': strVal(student.handphone),
        'Email': sanitizeCell(student.email),
        'Cita-cita': getLabel(student.m_life_goal),
        'Hobi': getLabel(student.m_hobby),

        // Pembelajaran / Rombel
        'Tahun Ajaran': sanitizeCell(ay.name || ay.year || student.academic_year_status),
        'Semester': ay.semester !== null && ay.semester !== undefined ? ay.semester : '',
        'Tingkat / Kelas': sg.level !== null && sg.level !== undefined ? sg.level : (la.m_level_id || student.tingkat || student.level_name || ''),
        'Rombel': sanitizeCell(sg.name || student.study_group_name),
        'Nomor Absen': la.absent_number !== null && la.absent_number !== undefined ? la.absent_number : '',
        'Wali Kelas': sanitizeCell(sg.teacher_name),
        'Ruang Kelas': sanitizeCell(sg.room_name),
        'Status Siswa': sanitizeCell(ss.name || student.reservation_registration),
        'Keterangan Status': sanitizeCell(sd.name),
        'Tanggal Masuk': sanitizeCell(la.admission_date || student.admission_date),

        // Vaksin
        'Vaksin Hepatitis B': hasVaccine(1, 'hepatitis') ? 'Ya' : 'Tidak',
        'Vaksin Polio': hasVaccine(2, 'polio') ? 'Ya' : 'Tidak',
        'Vaksin BCG': hasVaccine(3, 'bcg') ? 'Ya' : 'Tidak',
        'Vaksin Campak': hasVaccine(4, 'campak') ? 'Ya' : 'Tidak',
        'Vaksin DPT': hasVaccine(5, 'dpt') ? 'Ya' : 'Tidak',
        'Vaksin Covid-19': hasVaccine(6, 'covid') ? 'Ya' : 'Tidak',

        // Bantuan
        'Sumber Dana Pendidikan': getLabel(student.m_fund_source),
        'Nomor KIP': strVal(student.nomor_kip),
        'KIP Tersinkron': student.is_kip_sync ? 'Ya' : 'Tidak',

        // Orang Tua: Ayah
        'Nama Ayah': sanitizeCell(parents.father_full_name),
        'Status Ayah': getLabel(parents.father_life_status),
        'Pendidikan Ayah': getLabel(parents.father_last_education || parents.father_latest_education),
        'Pekerjaan Ayah': getLabel(parents.father_job || parents.father_m_job),
        'Penghasilan Ayah': getLabel(parents.father_average_income_per_month),
        'No HP Ayah': strVal(parents.father_phone_number),
        'Alamat Ayah': sanitizeCell(parents.father_address),

        // Orang Tua: Ibu
        'Nama Ibu': sanitizeCell(parents.mother_full_name),
        'Status Ibu': getLabel(parents.mother_life_status),
        'Pendidikan Ibu': getLabel(parents.mother_last_education || parents.mother_latest_education),
        'Pekerjaan Ibu': getLabel(parents.mother_job || parents.mother_m_job),
        'Penghasilan Ibu': getLabel(parents.mother_average_income_per_month),
        'No HP Ibu': strVal(parents.mother_phone_number),
        'Alamat Ibu': sanitizeCell(parents.mother_address),

        // Wali
        'Nama Wali': sanitizeCell(parents.wali_full_name),
        'Status Wali': getLabel(parents.wali_life_status),
        'Pekerjaan Wali': getLabel(parents.wali_job),
        'Penghasilan Wali': getLabel(parents.wali_average_income_per_month),
        'No HP Wali': strVal(parents.wali_phone_number)
      };

      // Sensitif
      if (allowNik) {
        row['NIK Siswa'] = strVal(student.nik);
      }
      if (allowKk) {
        row['No Kartu Keluarga'] = strVal(student.kk_num);
        row['Nama Kepala Keluarga'] = sanitizeCell(student.family_head_name);
      }
      if (allowParentsNik) {
        row['NIK Ayah'] = strVal(parents.father_nik);
        row['NIK Ibu'] = strVal(parents.mother_nik);
        row['NIK Wali'] = strVal(parents.wali_nik);
      }
      if (allowBansos) {
        row['Penerima PKH'] = student.pkh_family ? 'Ya' : 'Tidak';
        row['Penerima BPNT'] = student.bpnt_family ? 'Ya' : 'Tidak';
        row['Anggota PBI'] = student.pbi_member ? 'Ya' : 'Tidak';
        row['DTKS Aktif'] = student.dtks_is_active ? 'Ya' : 'Tidak';
      }
      if (allowCoords) {
        row['Latitude'] = student.latitude !== null && student.latitude !== undefined ? student.latitude : '';
        row['Longitude'] = student.longitude !== null && student.longitude !== undefined ? student.longitude : '';
      }

      rows.push(row);
    });

    return {
      sheetName: 'Data_Siswa_Lengkap',
      rows,
      stats: {
        totalFound: safeStudents.length,
        exportedCount: rows.length,
        duplicateCount
      }
    };
  }

  return {
    sanitizeCell,
    strVal,
    normalizeStudents,
    normalizeStudentsSingleSheet
  };
});
