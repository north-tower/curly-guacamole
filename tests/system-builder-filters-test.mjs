/**
 * Mirrors the country, handicap, race-type, and field rules in inc/system-builder.php.
 * Run: node tests/system-builder-filters-test.mjs
 */
const courses = {
    ireland: ['ballinrobe', 'bellewstown', 'clonmel', 'cork', 'curragh', 'down royal', 'downpatrick', 'dundalk', 'fairyhouse', 'galway', 'gowran', 'kilbeggan', 'killarney', 'laytown', 'leopardstown', 'limerick', 'listowel', 'mallow', 'naas', 'navan', 'phoenix park', 'powerstown', 'punchestown', 'roscommon', 'sligo', 'thurles', 'tipperary', 'tramore', 'wexford'],
    scotland: ['ayr', 'hamilton', 'kelso', 'musselburgh', 'perth'],
    wales: ['bangor on dee', 'bangor', 'chepstow', 'ffos las'],
    england: ['aintree', 'ascot', 'bath', 'beverley', 'brighton', 'carlisle', 'cartmel', 'catterick', 'chelmsford', 'cheltenham', 'chester', 'doncaster', 'epsom', 'exeter', 'fakenham', 'folkestone', 'fontwell', 'goodwood', 'great leighs', 'haydock', 'hereford', 'hexham', 'huntingdon', 'kempton', 'leicester', 'lingfield', 'ludlow', 'market rasen', 'newbury', 'newcastle', 'newmarket', 'newton abbot', 'nottingham', 'plumpton', 'pontefract', 'redcar', 'ripon', 'salisbury', 'sandown', 'sedgefield', 'southwell', 'stratford', 'taunton', 'thirsk', 'towcester', 'uttoxeter', 'warwick', 'wetherby', 'wincanton', 'windsor', 'wolverhampton', 'worcester', 'yarmouth', 'york']
};

function normalizeCourse(course) {
    let c = String(course || '').toLowerCase().replace(/[_-]/g, ' ').replace(/[^a-z0-9 ]+/g, ' ').replace(/\s+/g, ' ').trim();
    c = c.replace(/^the\s+/, '').replace(/\s+(park|bridge|downs|city|aw)$/, '');
    return c.trim();
}

function countryBucket(country) {
    const c = String(country || '').toLowerCase().trim();
    if (!c) return '';
    if (/ireland|\beire\b|\bire\b|\birl\b/.test(c)) return 'ireland';
    if (/scotland|\bsco\b/.test(c)) return 'scotland';
    if (/wales|cymru|\bwal\b/.test(c)) return 'wales';
    if (/england|\beng\b/.test(c)) return 'england';
    return '';
}

function courseCountry(course) {
    const key = normalizeCourse(course);
    if (!key) return '';
    const flat = {};
    Object.keys(courses).forEach((bucket) => {
        courses[bucket].forEach((name) => { flat[name] = bucket; });
    });
    if (flat[key]) return flat[key];
    const names = Object.keys(flat).sort((a, b) => b.length - a.length);
    for (const name of names) {
        if (new RegExp('(?:^| )' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(?: |$)').test(key)) {
            return flat[name];
        }
    }
    return '';
}

function meetingCountry(course, country) {
    return courseCountry(course) || countryBucket(country);
}

function countryMatches(course, country, selected) {
    const wanted = {};
    selected.forEach((item) => {
        const bucket = countryBucket(item);
        if (bucket) wanted[bucket] = true;
    });
    if (!Object.keys(wanted).length) return true;
    const bucket = meetingCountry(course, country);
    return bucket !== '' && !!wanted[bucket];
}

function handicapState(value) {
    if (value === null || value === undefined) return null;
    const v = String(value).toLowerCase().trim();
    if (!v) return null;
    if (/^-?\d+(?:\.\d+)?$/.test(v)) return parseInt(v, 10) === 1;
    if (/non[-\s]?handicap/.test(v) || /^(n|no|non|false)$/.test(v)) return false;
    if (/^(y|yes|true|hcap|handicap)$/.test(v) || v.indexOf('handicap') !== -1 || v.indexOf('hcap') !== -1) return true;
    return null;
}

function raceTypeMatches(raceType, wanted) {
    wanted = (wanted || []).filter((v) => v !== '');
    if (!wanted.length) return true;
    const t = String(raceType || '').toLowerCase().trim();
    if (!t) return false;
    return wanted.some((type) => {
        type = String(type).toLowerCase();
        if (type === 'nh flat') return t.indexOf('nh flat') !== -1 || t.indexOf('national hunt flat') !== -1 || t.indexOf('bumper') !== -1;
        if (type === 'flat') return t.indexOf('flat') !== -1 && t.indexOf('nh') === -1 && t.indexOf('national hunt') === -1 && t.indexOf('bumper') === -1;
        if (type === 'hurdle') return t.indexOf('hurdle') !== -1;
        if (type === 'chase') return t.indexOf('chase') !== -1 || t.indexOf('steeple') !== -1;
        return t.indexOf(type) !== -1;
    });
}

function speedFigure(row) {
    for (const key of ['wt_speed_rating', 'speed_rating', 'sr_lto']) {
        if (row[key] !== undefined && row[key] !== null && row[key] !== '' && !isNaN(Number(row[key]))) {
            return Number(row[key]);
        }
    }
    return null;
}

function between(value, min, max) {
    if ((min || '') === '' && (max || '') === '') return true;
    if (value === null || value === undefined || value === '') return false;
    const v = Number(value);
    if (min !== '' && min !== undefined && v < Number(min)) return false;
    if (max !== '' && max !== undefined && v > Number(max)) return false;
    return true;
}

function dosagePasses(row, filters) {
    const diMin = filters.di_min ?? '';
    const diMax = filters.di_max ?? '';
    if (diMin !== '' || diMax !== '') {
        if (row._dosage_di_infinite) {
            if (diMax !== '') return false;
        } else if (!between(row._dosage_di ?? null, diMin, diMax)) {
            return false;
        }
    }
    if (!between(row._dosage_cd ?? null, filters.cd_min ?? '', filters.cd_max ?? '')) {
        return false;
    }
    return true;
}

function passes(row, filters) {
    if (!countryMatches(row.course, row.country, filters.country || [])) return false;
    if (!raceTypeMatches(row.race_type, filters.race_type || [])) return false;
    if (filters.handicap === 'yes' || filters.handicap === 'no') {
        const state = handicapState(row.handicap);
        if (state === null || state !== (filters.handicap === 'yes')) return false;
    }
    const field = Number(row._field_size || 0);
    if ((filters.field_min || '') !== '' || (filters.field_max || '') !== '') {
        if (field < 1) return false;
        if (filters.field_min !== '' && field < Number(filters.field_min)) return false;
        if (filters.field_max !== '' && field > Number(filters.field_max)) return false;
    }
    if (!dosagePasses(row, filters)) return false;
    return true;
}

function qualify(rows, filters) {
    const byRace = {};
    rows.forEach((row) => {
        (byRace[row.race_id] = byRace[row.race_id] || []).push(row);
    });
    const out = [];
    Object.keys(byRace).forEach((id) => {
        const race = byRace[id];
        race.forEach((row) => { row._field_size = race.length; });
        let kept = race.filter((row) => passes(row, filters));
        if (filters.pick_mode === 'top_sr' && kept.length > 1) {
            kept = kept.filter((row) => speedFigure(row) !== null);
            kept.sort((a, b) => speedFigure(b) - speedFigure(a));
            kept = kept.length ? [kept[0]] : [];
        }
        kept.forEach((row) => out.push(row));
    });
    return out;
}

const user = {
    country: ['England'],
    race_type: ['Hurdle'],
    handicap: 'no',
    field_min: '8',
    field_max: '10',
    pick_mode: 'top_sr'
};

function race(id, course, country, type, handicap, speeds) {
    return speeds.map((speed, i) => ({
        race_id: id,
        horse: course + ' ' + (i + 1),
        course: course,
        country: country,
        race_type: type,
        handicap: handicap,
        speed_rating: speed
    }));
}

const fixture = [
    ...race(1, 'Leopardstown', '', 'Hurdle', 0, [70, 88, 81, 60, 55, 40, 33, 22, 11]),
    ...race(2, 'Fairyhouse', 'England', 'Hurdle', 0, [10, 20, 30, 40, 50, 60, 70, 90, 12]),
    ...race(3, 'Punchestown', 'IRE', 'Hurdle', 0, [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]),
    ...race(4, 'Ayr', 'GB', 'Hurdle', 0, [5, 6, 7, 8, 9, 10, 11, 12, 40]),
    ...race(5, 'Chepstow', '', 'Hurdle', 0, [5, 6, 7, 8, 9, 10, 11, 12, 13]),
    ...race(6, 'Ascot', '', 'Hurdle', 0, [40, 55, 70, 82, 91, 63, 44, 21, 15]),
    ...race(7, 'Haydock Park', '', 'Maiden Hurdle', 'Non-Handicap', [44, 80, null, 61, 50, 33, 22, 18]),
    ...race(8, 'Cheltenham', 'England', 'Flat', 0, [90, 80, 70, 60, 50, 40, 30, 20, 10]),
    ...race(9, 'Newbury', 'England', 'Hurdle', 1, [90, 80, 70, 60, 50, 40, 30, 20, 10]),
    ...race(10, 'Sandown Park', 'England', 'Hurdle', 0, [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 99]),
    ...race(11, 'Warwick', 'England', 'Hurdle', 0, [9, 8, 7, 6, 5, 4, 3]),
    ...race(12, 'Mystery Park', '', 'Hurdle', 0, [9, 8, 7, 6, 5, 4, 3, 2, 1])
];

const picked = qualify(fixture, user);
const names = picked.map((row) => row.course + ':' + row.horse + '@' + row.speed_rating + ' field ' + row._field_size);

function assert(cond, message) {
    if (!cond) {
        console.error('FAIL ' + message);
        process.exitCode = 1;
    }
}

assert(picked.length === 2, 'expected Ascot and Haydock only, got ' + JSON.stringify(names));
assert(picked.some((row) => row.course === 'Ascot' && row.speed_rating === 91), 'Ascot should keep the 91 speed figure');
assert(picked.some((row) => row.course === 'Haydock Park' && row.speed_rating === 80), 'Haydock should keep the 80 speed figure');
assert(!picked.some((row) => /Leopardstown|Fairyhouse|Punchestown|Ayr|Chepstow|Mystery/.test(row.course)), 'non-England courses must be absent');
assert(!picked.some((row) => row.course === 'Cheltenham' || row.course === 'Newbury' || row.course === 'Sandown Park' || row.course === 'Warwick'), 'wrong type, handicap, or field must be absent');
assert(countryMatches('Leopardstown (IRE)', '', ['England']) === false, 'Irish suffix course');
assert(countryMatches('The Curragh', '', ['England']) === false, 'Curragh');
assert(countryMatches('Catterick Bridge', '', ['England']) === true, 'Catterick');
assert(handicapState('Non-Handicap') === false, 'Non-Handicap label');
assert(handicapState(1) === true && handicapState(0) === false && handicapState(null) === null, 'numeric handicap');
assert(raceTypeMatches('NH Flat', ['Flat']) === false, 'NH Flat is not Flat');
assert(raceTypeMatches('Hurdles', ['Hurdle']) === true, 'Hurdles');
assert(raceTypeMatches('', ['Hurdle']) === false, 'blank race type');

const dosageRow = (di, cd, inf) => ({
    race_id: 99,
    course: 'Ascot',
    country: 'England',
    race_type: 'Flat',
    handicap: 0,
    speed_rating: 80,
    _dosage_di: di,
    _dosage_cd: cd,
    _dosage_di_infinite: !!inf
});

assert(dosagePasses(dosageRow(2.5, 0.6, false), { di_min: '2', cd_min: '0.5' }), 'dosage sprinter band');
assert(!dosagePasses(dosageRow(1.8, 0.6, false), { di_min: '2', cd_min: '0.5' }), 'dosage low DI');
assert(dosagePasses(dosageRow(null, 0.8, true), { di_min: '2' }), 'dosage Inf passes di_min only');
assert(!dosagePasses(dosageRow(null, 0.8, true), { di_max: '1.4' }), 'dosage Inf fails di_max');

if (!process.exitCode) {
    console.log('ok ' + names.join(' | '));
}
