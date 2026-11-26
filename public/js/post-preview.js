/**
 * Podgląd posta na żywo (Instagram, Facebook, TikTok, LinkedIn…): kadr, ucięcie opisu na „…więcej”, hashtagi.
 * Liczniki znaków i hashtagów z limitami platform wybranych w publikacjach.
 */
(function () {
    const form = document.querySelector('form.content-form[data-preview-accounts]');
    if (!form) return;
    const accounts = JSON.parse(form.dataset.previewAccounts || '{}');
    const limits = JSON.parse(form.dataset.limits || '{}');
    const media = JSON.parse(form.dataset.files || '[]');
    const desc = form.querySelector('[data-desc]'), tags = form.querySelector('[data-hashtags]');
    const stage = form.querySelector('[data-preview-stage]'), tabsEl = form.querySelector('[data-preview-tabs]'), note = form.querySelector('[data-preview-note]');
    const PL = document.documentElement.lang !== 'en';
    const T = PL ? {
        more: 'więcej', fbMore: 'Wyświetl więcej', now: 'Teraz', likes: 'Polubienia', chars: 'znaków', tags: 'hashtagów',
        cutIg: n => `Instagram pokazuje ok. ${n} pierwszych znaków — reszta po „…więcej”.`,
        cutFb: 'Długi tekst Facebook zwija po kilku linijkach („Wyświetl więcej”).',
        crop: (p, r) => `${p} przytnie zdjęcie do proporcji ${r}.`, reel: 'Film pokaże się jako rolka 9:16.',
        noMedia: 'Instagram wymaga zdjęcia albo filmu.', igReel: 'Rolka: pełny ekran 9:16 — ważne elementy trzymaj z dala od dolnej części (opis i przyciski).', igStory: 'Relacja: pełny ekran 9:16, bez opisu — tekst umieść na grafice. Znika po 24 h.', fbStory: 'Relacja na Facebooku: pełny ekran 9:16, bez opisu. Znika po 24 h.', pinTitle: n => `Tytuł pinu ma ${n} znaków — Pinterest pokaże najwyżej 100.`, pinMedia: 'Pin wymaga zdjęcia albo filmu.', pinRatio: 'Pinterest najlepiej wyświetla piny w proporcji 2:3 (np. 1000×1500 px). Tytuł pinu to pole „Tytuł”.', fbReel: 'Po kliknięciu film otwiera się na pełnym ekranie — opis skraca się do jednej linijki.', ttVideo: 'TikTok publikuje tylko film — dodaj plik wideo.', strictest: 'najostrzejszy limit',
        follow: 'Obserwuj', fbLike: 'Lubię to', fbComment: 'Komentarz', fbShare: 'Udostępnij',
    } : {
        more: 'more', fbMore: 'See more', now: 'Just now', likes: 'Likes', chars: 'characters', tags: 'hashtags',
        cutIg: n => `Instagram shows about the first ${n} characters — the rest after “…more”.`,
        cutFb: 'Facebook folds long text after a few lines (“See more”).',
        crop: (p, r) => `${p} will crop the image to ${r}.`, reel: 'The video will show as a 9:16 reel.',
        noMedia: 'Instagram requires a photo or video.', igReel: 'Reel: full screen 9:16 — keep key elements away from the bottom (caption and buttons).', igStory: 'Story: full screen 9:16, no caption — put text on the image. Disappears after 24 h.', fbStory: 'Facebook story: full screen 9:16, no caption. Disappears after 24 h.', pinTitle: n => `The pin title has ${n} characters — Pinterest shows at most 100.`, pinMedia: 'A pin needs a photo or video.', pinRatio: 'Pinterest shows pins best at 2:3 (e.g. 1000×1500 px). The pin title is the “Title” field.', fbReel: 'When tapped, the video opens full screen — the caption shrinks to one line.', ttVideo: 'TikTok publishes video only — add a video file.', strictest: 'strictest limit', follow: 'Follow', fbLike: 'Like', fbComment: 'Comment', fbShare: 'Share',
    };
    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const count = text => ({ chars: [...text].length, tags: (text.match(/(^|[^\p{L}\p{N}_])#[\p{L}\p{N}_]+/gu) || []).length });
    const textFor = caption => (caption && caption.trim()) ? caption : [desc.value.trim(), (tags?.value || '').trim()].filter(Boolean).join('\n\n');
    // Hashtagi, @wzmianki i linki na niebiesko; nowe linie zachowane.
    const rich = t => esc(t).replace(/(^|[\s(])([#@][\p{L}\p{N}_.]+)/gu, '$1<span class="pv-tag">$2</span>').replace(/\n/g, '<br>');

    // Nowo wgrane pliki (z przeglądarki) — od razu w podglądzie.
    form.addEventListener('upload-done', e => {
        const f = e.detail.file;
        if (/^image\//.test(f.type)) media.push({ url: URL.createObjectURL(f), video: false });
        else if (/^video\//.test(f.type) || /\.(mov|mp4|m4v|webm)$/i.test(f.name)) media.push({ url: null, video: true, blob: URL.createObjectURL(f) });
        render();
    });

    const ratioCache = {};
    const ratioOf = url => new Promise(res => {
        if (!url) return res(1);
        if (ratioCache[url]) return res(ratioCache[url]);
        const i = new Image(); i.onload = () => res(ratioCache[url] = i.naturalWidth / i.naturalHeight || 1); i.onerror = () => res(1); i.src = url;
    });

    // Wybrane w publikacjach konta (bez oznaczonych do usunięcia).
    const selected = () => [...form.querySelectorAll('[data-pub-row]')].map(row => {
        const acc = accounts[row.querySelector('[data-account-select]')?.value];
        if (!acc || row.querySelector('input[name$="[remove]"]')?.checked) return null;
        return { ...acc, row, caption: row.querySelector('[data-pub-caption]')?.value || '', promoted: !!row.querySelector('input[name$="[promoted]"]')?.checked };
    }).filter(Boolean);

    let active = null;
    const svg = (p, fill) => `<svg viewBox="0 0 24 24" width="24" height="24" fill="${fill || 'none'}" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${p}</svg>`;
    const I = {
        heart: svg('<path d="M16.8 3.5c-1.9 0-3.6 1-4.8 2.6C10.8 4.5 9.1 3.5 7.2 3.5A5.2 5.2 0 0 0 2 8.8c0 5.5 10 11.7 10 11.7s10-6.2 10-11.7a5.2 5.2 0 0 0-5.2-5.3Z"/>'),
        comment: svg('<path d="M20.7 16.5A9.5 9.5 0 1 0 17 20.3L21.5 21Z"/>'),
        send: svg('<path d="M22 3 9.2 10.1M22 3l-6.8 18-6-10.9L2 7.1Z"/>'),
        save: svg('<path d="M20 21 12 14.7 4 21V3h16Z"/>'),
        more: '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg>',
        fbLike: svg('<path d="M7 11v9H4v-9Zm0 0 4.2-7.2a1.6 1.6 0 0 1 3 .9L13.5 9H19a2 2 0 0 1 2 2.3l-1.2 7A2 2 0 0 1 17.8 20H7"/>'),
        fbComment: svg('<path d="M21 11.5c0 4.1-4 7.5-9 7.5-1.1 0-2.2-.2-3.2-.5L4 20l1.3-3.6C4.5 15 3 13.4 3 11.5 3 7.4 7 4 12 4s9 3.4 9 7.5Z"/>'),
        fbShare: svg('<path d="M14 5V2l7 7-7 7v-3.1C8 12.9 4.6 15 3 20c.5-7 4.6-13.6 11-15Z"/>'),
        globe: '<svg viewBox="0 0 16 16" width="12" height="12" aria-hidden="true"><circle cx="8" cy="8" r="7" fill="none" stroke="currentColor" stroke-width="1.4"/><path d="M1 8h14M8 1c2 2 2.8 4.3 2.8 7S10 13 8 15M8 1C6 3 5.2 5.3 5.2 8S6 13 8 15" fill="none" stroke="currentColor" stroke-width="1.2"/></svg>',
        play: '<svg viewBox="0 0 24 24" width="40" height="40" aria-hidden="true"><path d="M8 5v14l11-7z" fill="#fff"/></svg>',
        note: '<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path d="M9 18a3 3 0 1 1-2-2.8V5l12-2v12a3 3 0 1 1-2-2.8V7.2L9 8.9Z" fill="currentColor"/></svg>',
        ttHeart: '<svg viewBox="0 0 24 24" width="32" height="32" aria-hidden="true"><path d="M16.8 3.5c-1.9 0-3.6 1-4.8 2.6C10.8 4.5 9.1 3.5 7.2 3.5A5.2 5.2 0 0 0 2 8.8c0 5.5 10 11.7 10 11.7s10-6.2 10-11.7a5.2 5.2 0 0 0-5.2-5.3Z" fill="#fff"/></svg>',
        ttComment: '<svg viewBox="0 0 24 24" width="30" height="30" aria-hidden="true"><path d="M12 3C6.5 3 2 6.8 2 11.5c0 2.6 1.4 5 3.7 6.5L5 21.5l4.2-2.4c.9.2 1.8.3 2.8.3 5.5 0 10-3.8 10-8.4S17.5 3 12 3Z" fill="#fff"/></svg>',
        ttSave: '<svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true"><path d="M19 21 12 16l-7 5V4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1Z" fill="#fff"/></svg>',
        ttShare: '<svg viewBox="0 0 24 24" width="30" height="30" aria-hidden="true"><path d="M13 4v4C7 9 4 13 3 19c2.5-3.3 5.6-4.8 10-4.8V18l8-7Z" fill="#fff"/></svg>',
    };
    // Ikony ekranu TikTok (białe, wypełnione — jak w aplikacji).
    const W = (p, w = 30) => `<svg viewBox="0 0 24 24" width="${w}" height="${w}" aria-hidden="true">${p}</svg>`;
    const TT = {
        heart: W('<path d="M16.8 3.5c-1.9 0-3.6 1-4.8 2.6C10.8 4.5 9.1 3.5 7.2 3.5A5.2 5.2 0 0 0 2 8.8c0 5.5 10 11.7 10 11.7s10-6.2 10-11.7a5.2 5.2 0 0 0-5.2-5.3Z" fill="#fff"/>', 34),
        comment: W('<path d="M12 3C6.5 3 2 6.6 2 11c0 2.4 1.3 4.6 3.4 6.1L4.6 21l4.3-2.3c1 .2 2 .3 3.1.3 5.5 0 10-3.6 10-8S17.5 3 12 3Z" fill="#fff"/><circle cx="7.5" cy="11" r="1.4" fill="#333"/><circle cx="12" cy="11" r="1.4" fill="#333"/><circle cx="16.5" cy="11" r="1.4" fill="#333"/>', 32),
        save: W('<path d="M18.5 21.5 12 17l-6.5 4.5V4a1.5 1.5 0 0 1 1.5-1.5h10A1.5 1.5 0 0 1 18.5 4Z" fill="#fff"/>', 30),
        share: W('<path d="M13.5 4v4.3C7.2 9 3.8 13.2 3 19.5c2.4-3.4 5.8-5 10.5-5V19l8-7.5Z" fill="#fff"/>', 32),
        note: W('<path d="M9 18a3 3 0 1 1-2-2.8V5l12-2v12a3 3 0 1 1-2-2.8V7.2L9 8.9Z" fill="#fff"/>', 14),
        search: W('<circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="#fff" stroke-width="2.2"/><path d="m15.5 15.5 5 5" stroke="#fff" stroke-width="2.4" stroke-linecap="round"/>', 24),
        live: '<span class="pv-tt-live"><i></i>LIVE</span>',
        signal: W('<rect x="2" y="15" width="3" height="5" rx="1" fill="#fff"/><rect x="7" y="12" width="3" height="8" rx="1" fill="#fff"/><rect x="12" y="9" width="3" height="11" rx="1" fill="#fff"/><rect x="17" y="6" width="3" height="14" rx="1" fill="rgba(255,255,255,.4)"/>', 15),
        wifi: W('<path d="M2 9a15 15 0 0 1 20 0M5.5 12.5a10 10 0 0 1 13 0M9 16a5 5 0 0 1 6 0" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="19.5" r="1.6" fill="#fff"/>', 15),
        home: W('<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1Z" fill="#fff"/>', 24),
        friends: W('<circle cx="9" cy="8" r="3.5" fill="none" stroke="#fff" stroke-width="1.8"/><path d="M2.5 20c.5-3.5 3.2-5.5 6.5-5.5s6 2 6.5 5.5" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/><circle cx="17" cy="9" r="2.6" fill="none" stroke="#fff" stroke-width="1.6"/><path d="M17.5 14c2.2.3 3.6 2 4 4.5" fill="none" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/>', 24),
        inbox: W('<path d="M4 4h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1h-8l-4.5 3.5V17H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z" fill="none" stroke="#fff" stroke-width="1.8" stroke-linejoin="round"/><path d="M8 10.5h8" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/>', 24),
        profile: W('<circle cx="12" cy="8" r="4" fill="none" stroke="#fff" stroke-width="1.8"/><path d="M4 21c.7-4.2 3.9-6.5 8-6.5s7.3 2.3 8 6.5" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/>', 24),
    };
    // Ikony Instagrama (obrys, białe — ciemny motyw jak na telefonie).
    const O = (p, w = 26) => `<svg viewBox="0 0 24 24" width="${w}" height="${w}" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${p}</svg>`;
    const IG = {
        heart: O('<path d="M16.8 3.5c-1.9 0-3.6 1-4.8 2.6C10.8 4.5 9.1 3.5 7.2 3.5A5.2 5.2 0 0 0 2 8.8c0 5.5 10 11.7 10 11.7s10-6.2 10-11.7a5.2 5.2 0 0 0-5.2-5.3Z"/>'),
        comment: O('<path d="M20.7 16.5A9.5 9.5 0 1 0 17 20.3L21.5 21Z"/>'),
        repost: O('<path d="M17 2.5 20.5 6 17 9.5"/><path d="M3.5 11V9a3 3 0 0 1 3-3h14"/><path d="M7 21.5 3.5 18 7 14.5"/><path d="M20.5 13v2a3 3 0 0 1-3 3h-14"/>'),
        send: O('<path d="M22 3 9.2 10.1M22 3l-6.8 18-6-10.9L2 7.1Z"/>'),
        save: O('<path d="M20 21 12 14.7 4 21V3h16Z"/>'),
        menu: O('<path d="M4 8h16M8 15h12"/>', 24),
        close: O('<path d="M5 5l14 14M19 5 5 19"/>', 24),
        back: O('<path d="m15 4-8 8 8 8"/>', 24),
        plus: O('<path d="M12 4v16M4 12h16"/>', 24),
        mute: O('<path d="M11 5 6 9H3v6h3l5 4Z"/><path d="m16 9 6 6M22 9l-6 6"/>', 16),
        note: '<svg viewBox="0 0 24 24" width="11" height="11" aria-hidden="true"><path d="M9 18a3 3 0 1 1-2-2.8V5l12-2v12a3 3 0 1 1-2-2.8V7.2L9 8.9Z" fill="currentColor"/></svg>',
        home: '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M3 10.3 12 3l9 7.3V21h-6.5v-6.5h-5V21H3Z" fill="#fff"/></svg>',
        reels: O('<rect x="3" y="3" width="18" height="18" rx="5"/><path d="m10 8.5 5.5 3.5-5.5 3.5Z"/>', 24),
        search: O('<circle cx="10.5" cy="10.5" r="7"/><path d="m16 16 5.5 5.5"/>', 24),
    };
    // Ikony Facebooka (nowy układ: liczniki przy ikonach).
    const FB = {
        like: O('<path d="M7 11v9.5H4V11Zm0 0 4.3-7.3c.9-1.5 3.2-.8 3.1 1L14 9h5.5a2 2 0 0 1 2 2.4l-1.4 7.4a2 2 0 0 1-2 1.7H7"/>'),
        likeSolid: '<svg viewBox="0 0 24 24" width="12" height="12" aria-hidden="true"><path d="M7 11v9.5H4V11Zm0 0 4.3-7.3c.9-1.5 3.2-.8 3.1 1L14 9h5.5a2 2 0 0 1 2 2.4l-1.4 7.4a2 2 0 0 1-2 1.7H7" fill="#fff"/></svg>',
        repost: O('<path d="M7 3v4h4"/><path d="M4 7h11a4 4 0 0 1 4 4v1"/><path d="M17 21v-4h-4"/><path d="M20 17H9a4 4 0 0 1-4-4v-1"/>'),
        share: O('<path d="M14 5V2l7 7-7 7v-3.1C8 12.9 4.6 15 3 20c.5-7 4.6-13.6 11-15Z"/>'),
        shareSolid: '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M14 5V2l7 7-7 7v-3.1C8 12.9 4.6 15 3 20c.5-7 4.6-13.6 11-15Z" fill="currentColor"/></svg>',
        dots: '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><circle cx="5" cy="12" r="1.8" fill="currentColor"/><circle cx="12" cy="12" r="1.8" fill="currentColor"/><circle cx="19" cy="12" r="1.8" fill="currentColor"/></svg>',
        chevron: O('<path d="m6 9 6 6 6-6"/>', 24),
        globe: '<svg viewBox="0 0 16 16" width="12" height="12" aria-hidden="true"><circle cx="8" cy="8" r="6.6" fill="none" stroke="currentColor" stroke-width="1.4"/><path d="M1.5 8h13M8 1.4c1.8 1.9 2.6 4.1 2.6 6.6S9.8 12.7 8 14.6M8 1.4C6.2 3.3 5.4 5.5 5.4 8s.8 4.7 2.6 6.6" fill="none" stroke="currentColor" stroke-width="1.2"/></svg>',
    };
    const PIN = { upload: O('<path d="M12 15V3"/><path d="m7 8 5-5 5 5"/><path d="M4 14v5a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"/>') };
    let fbChoice = null;
    const fbMode = () => fbChoice || ({ reel: 'reel', story: 'story' }[form.querySelector('select[name=type]')?.value] || 'post');

    // Pasek stanu telefonu (godzina, zasięg, Wi-Fi, bateria).
    const statusBar = () => {
        const d = new Date();
        return `<div class="pv-status"><strong>${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}</strong><span>${TT.signal}${TT.wifi}<i class="pv-tt-batt">80</i></span></div>`;
    };
    // Widok Instagrama: wybrany ręcznie albo wg typu treści (rolka / relacja / post).
    let igChoice = null;
    const igMode = () => igChoice || ({ reel: 'reel', story: 'story' }[form.querySelector('select[name=type]')?.value] || 'post');

    // Zdjęcie profilu konta (albo inicjał na gradiencie).
    const avatar = (acc, cls = '') => acc.avatar
        ? `<span class="pv-avatar ${cls}"><img src="${esc(acc.avatar)}" alt=""></span>`
        : `<span class="pv-avatar ${cls}">${esc((acc.name || acc.handle || '?').trim().slice(0, 1).toUpperCase())}</span>`;
    const L = PL ? {
        likesIg: 'Polubienia: 0', comments: 'Zobacz wszystkie komentarze', igFollow: 'Obserwuj', igSound: 'Oryginalny dźwięk', igNow: 'Teraz', igReels: 'Reels', igFriends: 'Znajomi', igSendMsg: 'Wyślij wiadomość…', igAgo: '1 min', igOpenReel: 'Kliknij, aby otworzyć jako rolkę', fbAgo: '1 min', fbAgoFeed: 'Teraz', fbMoreFeed: 'Zobacz więcej', fbMoreShort: 'więcej', fbAddComment: 'Dodaj komentarz', fbOpenReel: 'Kliknij, aby otworzyć film', fbModes: { post: 'Post', reel: 'Film / rolka', story: 'Relacja' }, pinVisit: 'Odwiedź', pinSave: 'Zapisz', pinNoTitle: 'Tytuł pinu (pole „Tytuł”)', igModes: { post: 'Post', reel: 'Rolka', story: 'Relacja' }, addComment: 'Dodaj komentarz…', sponsored: 'Sponsorowane',
        fbLike: 'Lubię to!', fbComment: 'Komentarz', fbShare: 'Udostępnij', fbWrite: 'Napisz komentarz…', fbReact: 'Ty i 0 innych osób',
        liLike: 'Poleć', liComment: 'Komentarz', liRepost: 'Opublikuj ponownie', liSend: 'Wyślij', followers: 'obserwujących', sound: 'oryginalny dźwięk', ttFollowing: 'Obserwowani', ttForYou: 'Dla Ciebie', ttCommunity: 'Społeczność', ttHome: 'Główna', ttFriends: 'Znajomi', ttInbox: 'Przychodzące', ttProfile: 'Profil',
    } : {
        likesIg: '0 likes', comments: 'View all comments', igFollow: 'Follow', igSound: 'Original audio', igNow: 'Just now', igReels: 'Reels', igFriends: 'Friends', igSendMsg: 'Send message…', igAgo: '1m', igOpenReel: 'Click to open as a reel', fbAgo: '1m', fbAgoFeed: 'Just now', fbMoreFeed: 'See more', fbMoreShort: 'more', fbAddComment: 'Add a comment', fbOpenReel: 'Click to open the video', fbModes: { post: 'Post', reel: 'Video / reel', story: 'Story' }, pinVisit: 'Visit', pinSave: 'Save', pinNoTitle: 'Pin title (the “Title” field)', igModes: { post: 'Post', reel: 'Reel', story: 'Story' }, addComment: 'Add a comment…', sponsored: 'Sponsored',
        fbLike: 'Like', fbComment: 'Comment', fbShare: 'Share', fbWrite: 'Write a comment…', fbReact: 'You and 0 others',
        liLike: 'Like', liComment: 'Comment', liRepost: 'Repost', liSend: 'Send', followers: 'followers', sound: 'original sound', ttFollowing: 'Following', ttForYou: 'For You', ttCommunity: 'Community', ttHome: 'Home', ttFriends: 'Friends', ttInbox: 'Inbox', ttProfile: 'Profile',
    };

    const images = () => media.filter(m => !m.video);
    const mediaBox = async (platform, notes, cls = '') => {
        const first = media[0];
        if (!first) return `<div class="pv-media pv-empty ${cls}" style="aspect-ratio:1/1"><span>🖼</span></div>`;
        if (first.video) {
            notes.push(T.reel);
            return `<div class="pv-media pv-video ${cls}" style="aspect-ratio:${platform === 'Facebook' || cls.includes('pv-dark') ? '4/5' : '9/16'}">${first.url ? `<img src="${esc(first.url)}" alt="">` : ''}<span class="pv-play">${I.play}</span></div>`;
        }
        const r = await ratioOf(first.url);
        // Instagram i Facebook w kanale: od 4:5 (pion) do 1.91:1 (poziom).
        const shown = Math.min(1.91, Math.max(0.8, r));
        if (Math.abs(shown - r) > 0.01) notes.push(T.crop(platform, r < 0.8 ? '4:5' : '1.91:1'));
        const n = images().length;
        const counter = n > 1 && platform === 'Instagram' ? `<span class="pv-count">1/${Math.min(n, 10)}</span>` : '';
        const dots = n > 1 && platform === 'Instagram' ? `<div class="pv-dots">${images().slice(0, 10).map((_, i) => `<i${i ? '' : ' class="on"'}></i>`).join('')}</div>` : '';
        // Facebook: kilka zdjęć jako kolaż (duże + mniejsze).
        if (n > 1 && platform !== 'Instagram') {
            const rest = images().slice(1, 3);
            return `<div class="pv-fb-grid pv-fb-grid-${rest.length}"><div class="pv-media" style="aspect-ratio:${shown.toFixed(3)}"><img src="${esc(first.url)}" alt=""></div>`
                + rest.map((m, i) => `<div class="pv-media"><img src="${esc(m.url)}" alt="">${i === rest.length - 1 && n > 3 ? `<span class="pv-more-photos">+${n - 3}</span>` : ''}</div>`).join('') + '</div>';
        }
        return `<div class="pv-media ${cls}" style="aspect-ratio:${shown.toFixed(3)}"><img src="${esc(first.url)}" alt="">${counter}${dots}</div>`;
    };

    const cut = (text, n) => {
        const chars = [...text];
        const lines = text.split('\n');
        let short = chars.length > n ? chars.slice(0, n).join('') : text;
        if (lines.length > 2 && short.split('\n').length > 2) short = lines.slice(0, 2).join('\n');
        // Ucięcie na granicy słowa, bez kropki/przecinka przed „…”.
        return short.length < text.length ? short.replace(/\s+\S*$/, '').replace(/[\s.,;:!?…-]+$/u, '') : null;
    };
    const caption = (text, n, moreLabel, prefix = '') => {
        const short = cut(text, n);
        return `<div class="pv-caption" data-full="${esc(text)}" data-prefix="${esc(prefix)}">${prefix}${short ? rich(short) + `… <button type="button" class="pv-more">${moreLabel}</button>` : rich(text)}</div>`;
    };

    const views = {
        // Instagram: post w kanale, rolka (pełny ekran) albo relacja — wg typu treści; kliknięcie filmu w poście otwiera rolkę.
        async instagram(acc, text, notes) {
            const n = limits.instagram.more;
            if (!media.length) notes.push(T.noMedia);
            const mode = igMode();
            const first = media[0];
            const bg = first?.url ? `<img class="pv-bg" src="${esc(first.url)}" alt="">` : '<span class="pv-bg pv-bg-empty"></span>';
            const verified = '<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path d="M12 1.5 14.6 3.4l3.2-.2 1 3 2.7 1.8-.9 3.1.9 3.1-2.7 1.8-1 3-3.2-.2L12 22.5l-2.6-1.9-3.2.2-1-3-2.7-1.8.9-3.1-.9-3.1 2.7-1.8 1-3 3.2.2Z" fill="#0095f6"/><path d="m7.8 12.2 2.8 2.8 5.6-5.8" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            const status = statusBar();

            if (mode === 'story') {
                notes.push(T.igStory);
                return `<div class="pv pv-phone pv-ig-story"><div class="pv-screen">${status}
                    <div class="pv-story-media">${bg}
                        <div class="pv-story-bars">${(images().length > 1 ? images().slice(0, 6) : [1]).map((_, i) => `<i class="${i ? '' : 'on'}"></i>`).join('')}</div>
                        <div class="pv-story-head">${avatar(acc, 'pv-sm')}<strong>${esc(acc.handle)}</strong><small>${L.igAgo}</small><span class="pv-spacer"></span>${IG.menu}${IG.close}</div>
                        ${first?.video ? `<span class="pv-play">${I.play}</span>` : ''}
                    </div>
                    <div class="pv-story-foot"><span class="pv-story-input">${L.igSendMsg}</span>${IG.heart}${IG.send}</div>
                </div></div>`;
            }

            if (mode === 'reel') {
                if (cut(text, 60)) notes.push(T.cutIg(60));
                notes.push(T.igReel);
                return `<div class="pv pv-phone pv-ig-reel"><div class="pv-screen">${bg}<span class="pv-shade"></span>${status}
                    <div class="pv-reel-top">${IG.back}<strong>${L.igReels}</strong><span>${L.igFriends}</span><span class="pv-spacer"></span>${IG.plus}</div>
                    ${first?.video ? '' : ''}
                    <div class="pv-reel-side">
                        <span>${IG.heart}<small>0</small></span><span>${IG.comment}<small>0</small></span>
                        <span>${IG.repost}<small>0</small></span><span>${IG.send}<small>0</small></span>
                        <span>${IG.menu}</span><span class="pv-reel-audio">${avatar(acc, 'pv-square-sm')}</span>
                    </div>
                    <div class="pv-reel-info">
                        <div class="pv-reel-user">${avatar(acc, 'pv-ring pv-md')}<strong>${esc(acc.handle)}</strong>${acc.verified ? verified : ''}<span class="pv-follow">${L.igFollow}</span></div>
                        ${caption(text, 60, '', '')}
                    </div>
                    <div class="pv-reel-progress"><i></i></div>
                    <div class="pv-reel-comment"><span>${L.addComment}</span></div>
                </div></div>`;
            }

            if (cut(text, n)) notes.push(T.cutIg(n));
            const isVideo = !!first?.video;
            return `<div class="pv pv-phone pv-ig-feed"><div class="pv-screen pv-screen-dark">${status}
                <div class="pv-feed-scroll">
                    <div class="pv-ig-head">${avatar(acc, 'pv-ring pv-md')}
                        <span class="pv-who"><strong>${esc(acc.handle)}</strong>${acc.promoted ? `<small>${L.sponsored}</small>` : (isVideo ? `<small>${IG.note} ${esc(acc.handle)} · ${L.igSound}</small>` : '')}</span>
                        <span class="pv-follow">${L.igFollow}</span>${IG.menu}</div>
                    <div class="pv-ig-media ${isVideo ? 'is-video' : ''}" ${isVideo ? `data-open-reel title="${esc(L.igOpenReel)}"` : ''}>${await mediaBox('Instagram', notes, 'pv-dark')}${isVideo ? `<span class="pv-mute">${IG.mute}</span>` : ''}</div>
                    <div class="pv-ig-actions"><span>${IG.heart}<b>0</b></span><span>${IG.comment}<b>0</b></span><span>${IG.repost}<b>0</b></span><span>${IG.send}<b>0</b></span><span class="pv-spacer"></span>${IG.save}</div>
                    ${caption(text, n, T.more, `<strong>${esc(acc.handle)}</strong> `)}
                    <div class="pv-ig-time">${L.igNow}</div>
                </div>
                <nav class="pv-ig-nav"><span class="on">${IG.home}</span><span>${IG.reels}</span><span>${IG.send}</span><span>${IG.search}</span><span>${avatar(acc, 'pv-xs')}</span></nav>
            </div></div>`;
        },
        // Facebook: post w kanale (jasny), film po kliknięciu (ciemny, pełny ekran) albo relacja — wg typu treści.
        async facebook(acc, text, notes) {
            const mode = fbMode();
            const first = media[0];
            const bg = first?.url ? `<img class="pv-bg" src="${esc(first.url)}" alt="">` : '<span class="pv-bg pv-bg-empty"></span>';
            const status = statusBar();
            const reacts = `<span class="pv-fb-reacts"><i class="r-like">${FB.likeSolid}</i><i class="r-love">❤</i></span>`;

            if (mode === 'story') {
                notes.push(T.fbStory);
                return `<div class="pv pv-phone pv-fb-story"><div class="pv-screen">${status}
                    <div class="pv-story-media">${bg}
                        <div class="pv-story-bars">${(images().length > 1 ? images().slice(0, 6) : [1]).map((_, i) => `<i class="${i ? '' : 'on'}"></i>`).join('')}</div>
                        <div class="pv-story-head pv-fb-story-head">${avatar(acc, 'pv-md')}<span class="pv-who"><strong>${esc(acc.name)} <small>${L.fbAgo}</small></strong></span><span class="pv-spacer"></span>${FB.chevron}${FB.dots}${IG.close}</div>
                        ${first?.video ? `<span class="pv-fb-mute">${IG.mute}</span><span class="pv-play">${I.play}</span>` : ''}
                    </div>
                    <div class="pv-fb-story-foot"><span class="pv-fb-sq">${FB.repost}</span><span class="pv-fb-share-pill">${FB.shareSolid} ${L.fbShare}</span>
                        <i class="pv-fb-bubble love">❤</i><i class="pv-fb-bubble like">${FB.likeSolid}</i><i class="pv-fb-bubble haha">😆</i></div>
                </div></div>`;
            }

            if (mode === 'reel') {
                notes.push(T.fbReel);
                return `<div class="pv pv-phone pv-fb-reel"><div class="pv-screen">${bg}<span class="pv-shade"></span>${status}
                    <div class="pv-fb-reel-top">${IG.back}<span class="pv-spacer"></span>${IG.search}<span class="pv-fb-circle">${FB.dots}</span></div>
                    <div class="pv-reel-side pv-fb-reel-side">
                        <span>${FB.like}<small>0</small></span><span>${IG.comment}<small>0</small></span><span>${FB.repost}</span>
                        <span>${FB.share}<small>0</small></span><span>${IG.save}</span>
                    </div>
                    <div class="pv-reel-info pv-fb-reel-info">
                        <div class="pv-reel-user">${avatar(acc, 'pv-md pv-fb-ring')}<strong>${esc(acc.name)}</strong>${FB.globe}<span class="pv-follow">${L.igFollow}</span></div>
                        ${caption(text, 45, L.fbMoreShort)}
                    </div>
                    <span class="pv-fb-reel-dots">${FB.dots}</span>
                    <div class="pv-reel-comment pv-fb-reel-comment"><span>${L.fbAddComment}<i>☺</i><i class="gif">GIF</i></span></div>
                </div></div>`;
            }

            if (cut(text, 60)) notes.push(T.cutFb);
            const isVideo = !!first?.video;
            return `<div class="pv pv-phone pv-fb-feed"><div class="pv-screen pv-screen-light">${status}
                <div class="pv-feed-scroll">
                    <div class="pv-fb-head">${avatar(acc, 'pv-fb-ring')}
                        <span class="pv-who"><span class="pv-fb-name"><strong>${esc(acc.name)}</strong> · <b>${L.igFollow}</b></span>
                        <small>${acc.promoted ? L.sponsored : L.fbAgoFeed} · ${FB.globe}</small></span>${FB.dots}<span class="pv-fb-x">${IG.close}</span></div>
                    <div class="pv-fb-text1">${caption(text, 60, L.fbMoreFeed)}</div>
                    ${media.length ? `<div class="pv-ig-media ${isVideo ? 'is-video' : ''}" ${isVideo ? `data-open-fb-reel title="${esc(L.fbOpenReel)}"` : ''}>${await mediaBox('Facebook', notes)}${isVideo ? `<span class="pv-mute pv-fb-mute2">${IG.mute}</span>` : ''}</div>` : ''}
                    <div class="pv-fb-bar"><span>${FB.like}<b>0</b></span><span>${IG.comment}<b>0</b></span><span>${FB.repost}</span><span>${FB.share}<b>0</b></span><span class="pv-spacer"></span>${reacts}</div>
                    <div class="pv-fb-next" aria-hidden="true"><div><i class="av"></i><span><i></i><i class="short"></i></span></div><i class="line"></i><i class="img"></i></div>
                </div>
            </div></div>`;
        },
        async tiktok(acc, text, notes) {
            if (!media.some(m => m.video)) notes.push(T.ttVideo);
            const bg = media[0]?.url ? `<img src="${esc(media[0].url)}" alt="">` : '';
            const now = new Date();
            const clock = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
            return `<div class="pv pv-tt"><div class="pv-tt-screen">${bg}
                <div class="pv-tt-status"><strong>${clock}</strong><span>${TT.signal}${TT.wifi}<i class="pv-tt-batt">80</i></span></div>
                <div class="pv-tt-top">${TT.live}<span>${L.ttCommunity}</span><span>${L.ttFollowing}</span><strong>${L.ttForYou}</strong>${TT.search}</div>
                <div class="pv-tt-side">
                    <span class="pv-tt-av">${avatar(acc)}<i>+</i></span>
                    <span>${TT.heart}<small>0</small></span>
                    <span>${TT.comment}<small>0</small></span>
                    <span>${TT.save}<small>0</small></span>
                    <span>${TT.share}<small>0</small></span>
                    <span class="pv-tt-disc">${avatar(acc)}</span>
                </div>
                <div class="pv-tt-text"><strong>${esc(acc.name)}</strong>${caption(text, limits.tiktok.more, T.more)}
                    <div class="pv-tt-sound">${TT.note}<span>${L.sound} - ${esc(acc.name)}</span></div></div>
                <nav class="pv-tt-nav">
                    <span>${TT.home}<small>${L.ttHome}</small></span><span>${TT.friends}<small>${L.ttFriends}</small></span>
                    <span class="pv-tt-plus"><i>+</i></span>
                    <span>${TT.inbox}<small>${L.ttInbox}</small></span><span>${TT.profile}<small>${L.ttProfile}</small></span>
                </nav></div></div>`;
        },
        // Pinterest: pin w widoku szczegółów (ciemny) — tytuł pinu = tytuł treści, pod nim opis.
        async pinterest(acc, text, notes) {
            const first = media[0];
            const title = (form.querySelector('input[name=title]')?.value || '').trim();
            if ([...title].length > 100) notes.push(T.pinTitle([...title].length));
            if (!media.length) notes.push(T.pinMedia);
            notes.push(T.pinRatio);
            const r = first?.url && !first.video ? await ratioOf(first.url) : 2 / 3;
            // Pinterest najlepiej wyświetla 2:3; bardzo wysokie piny przycina.
            const shown = Math.max(0.5, Math.min(1.91, r));
            const pin = first?.url ? `<img src="${esc(first.url)}" alt="">` : '<span class="pv-bg-empty pv-bg"></span>';
            return `<div class="pv pv-phone pv-pin"><div class="pv-screen">${statusBar()}
                <div class="pv-pin-scroll">
                    <div class="pv-pin-card" style="aspect-ratio:${shown.toFixed(3)}">${pin}${first?.video ? `<span class="pv-play">${I.play}</span>` : ''}
                        <span class="pv-pin-btn left">${IG.back}</span><span class="pv-pin-btn right">${FB.dots}</span></div>
                    <div class="pv-pin-actions"><span>${IG.heart}<b>0</b></span><span>${IG.comment}<b>0</b></span><span>${PIN.upload}</span>
                        <span class="pv-spacer"></span><span class="pv-pin-visit">${L.pinVisit}</span><span class="pv-pin-save">${L.pinSave}</span></div>
                    <div class="pv-pin-user">${avatar(acc, 'pv-xs')}<span>${esc(acc.name)}</span></div>
                    <div class="pv-pin-title"><strong>${esc(title || L.pinNoTitle)}</strong><span class="pv-pin-chev">${FB.chevron}</span></div>
                    ${caption(text, 120, L.fbMoreShort)}
                </div>
            </div></div>`;
        },
        async linkedin(acc, text, notes) {
            return `<div class="pv pv-fb pv-li">
                <div class="pv-head">${avatar(acc, 'pv-square')}<span class="pv-who"><strong>${esc(acc.name)}</strong><small>0 ${L.followers}</small><small>${acc.promoted ? L.sponsored : T.now} · ${I.globe}</small></span><span class="pv-menu">${I.more}</span></div>
                <div class="pv-fb-text">${caption(text, limits.linkedin.more, T.more)}</div>
                ${media.length ? await mediaBox('LinkedIn', notes) : ''}
                <div class="pv-fb-actions pv-li-actions"><span>${I.fbLike} ${L.liLike}</span><span>${I.fbComment} ${L.liComment}</span><span>${I.fbShare} ${L.liRepost}</span><span>${I.send} ${L.liSend}</span></div></div>`;
        },
    };
    views.youtube = views.linkedin;

    // ---------------------------------------------------------------- liczniki
    const counterHtml = (text, platforms) => {
        const c = count(text);
        const lims = platforms.map(p => ({ p, ...limits[p.platform] })).filter(l => l.chars);
        if (!lims.length) return `<span>${c.chars} ${T.chars} · ${c.tags} ${T.tags}</span>`;
        const charL = lims.reduce((a, b) => (b.chars < a.chars ? b : a));
        const tagLs = lims.filter(l => l.tags);
        const tagL = tagLs.length ? tagLs.reduce((a, b) => (b.tags < a.tags ? b : a)) : null;
        const pct = c.chars / charL.chars;
        return `<span class="${c.chars > charL.chars ? 'over' : pct > .9 ? 'near' : ''}">${c.chars} / ${charL.chars} ${T.chars} <small>(${esc(charL.p.label)})</small></span>`
            + (tagL ? ` · <span class="${c.tags > tagL.tags ? 'over' : ''}">${c.tags} / ${tagL.tags} ${T.tags} <small>(${esc(tagL.p.label)})</small></span>` : ` · <span>${c.tags} ${T.tags}</span>`)
            + `<i class="text-bar"><i style="width:${Math.min(100, pct * 100).toFixed(1)}%" class="${c.chars > charL.chars ? 'over' : pct > .9 ? 'near' : ''}"></i></i>`;
    };

    const counters = sel => {
        // Główny licznik: tekst wspólny dla kont bez własnego opisu.
        const shared = sel.filter(s => !s.caption.trim());
        form.querySelector('[data-counter="main"]').innerHTML = counterHtml(textFor(''), shared.length ? shared : sel);
        form.querySelectorAll('[data-pub-row]').forEach(row => {
            const box = row.querySelector('[data-counter="pub"]'), cap = row.querySelector('[data-pub-caption]');
            if (!box || !cap) return;
            const acc = accounts[row.querySelector('[data-account-select]')?.value];
            box.innerHTML = cap.value.trim() && acc ? counterHtml(cap.value, [acc]) : '';
        });
    };

    // ---------------------------------------------------------------- render
    let seq = 0;
    const render = async () => {
        const my = ++seq;
        const sel = selected();
        counters(sel);
        const list = sel.length ? sel : [{ platform: 'instagram', label: 'Instagram', handle: 'profil', name: 'Profil', caption: '' }];
        // Jedna karta na konto (Instagram i Facebook tego samego klienta obok siebie w zakładkach).
        const keys = list.map((s, i) => s.platform + ':' + i);
        if (!keys.includes(active)) active = keys[0];
        tabsEl.innerHTML = list.map((s, i) => `<button type="button" role="tab" class="chip chip-sm${keys[i] === active ? ' chip-dark' : ''}" data-key="${keys[i]}" aria-selected="${keys[i] === active}">${esc(s.label)}${list.filter(x => x.platform === s.platform).length > 1 ? ' · ' + esc(s.handle) : ''}</button>`).join('');
        const cur = list[keys.indexOf(active)];
        const notes = [];
        const html = await (views[cur.platform] || views.linkedin)(cur, textFor(cur.caption), notes);
        if (my !== seq) return;
        const modeSet = { instagram: [L.igModes, igMode(), 'ig'], facebook: [L.fbModes, fbMode(), 'fb'] }[cur.platform];
        const modes = modeSet
            ? `<div class="pv-modes" role="group">${Object.entries(modeSet[0]).map(([k, v]) => `<button type="button" class="${modeSet[1] === k ? 'on' : ''}" data-${modeSet[2]}-mode="${k}">${v}</button>`).join('')}</div>` : '';
        stage.innerHTML = modes + html;
        note.textContent = notes.join(' ');
    };

    tabsEl.addEventListener('click', e => { const b = e.target.closest('[data-key]'); if (b) { active = b.dataset.key; render(); } });
    stage.addEventListener('click', e => {
        const m = e.target.closest('[data-ig-mode]');
        if (m) { igChoice = m.dataset.igMode; render(); return; }
        if (e.target.closest('[data-open-reel]')) { igChoice = 'reel'; render(); return; }
        const f = e.target.closest('[data-fb-mode]');
        if (f) { fbChoice = f.dataset.fbMode; render(); return; }
        if (e.target.closest('[data-open-fb-reel]')) { fbChoice = 'reel'; render(); return; }
        const b = e.target.closest('.pv-more'); if (!b) return;
        const cap = b.closest('.pv-caption');
        cap.innerHTML = cap.dataset.prefix + rich(cap.dataset.full);
    });
    let t;
    const later = () => { clearTimeout(t); t = setTimeout(render, 150); };
    form.addEventListener('input', later);
    form.addEventListener('change', e => { if (e.target.name === 'type') { igChoice = null; fbChoice = null; } later(); });
    render();
})();
