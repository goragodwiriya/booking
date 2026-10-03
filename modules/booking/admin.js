EventManager.on('router:initialized', () => {
    RouterManager.register('/rooms', {
        template: 'booking/catalog.html',
        title: '{LNG_All rooms}',
        requireAuth: true
    });

    RouterManager.register('/my-bookings', {
        template: 'booking/my-bookings.html',
        title: '{LNG_My bookings}',
        requireAuth: true
    });

    RouterManager.register('/booking', {
        template: 'booking/booking.html',
        title: '{LNG_Book a room}',
        requireAuth: true
    });

    RouterManager.register('/approvals', {
        template: 'booking/approvals.html',
        title: '{LNG_Booking approvals}',
        requireAuth: true
    });

    RouterManager.register('/booking-review', {
        template: 'booking/review.html',
        title: '{LNG_Review booking}',
        menuPath: '/approvals',
        requireAuth: true
    });

    RouterManager.register('/room-management', {
        template: 'booking/rooms.html',
        title: '{LNG_Rooms}',
        requireAuth: true
    });

    RouterManager.register('/booking-settings', {
        template: 'booking/settings.html',
        title: '{LNG_Settings}',
        requireAuth: true
    });

    RouterManager.register('/booking-categories', {
        template: 'booking/categories.html',
        title: '{LNG_Categories}',
        requireAuth: true
    });

    RouterManager.register('/', {
        template: 'booking/calendar.html',
        title: '{LNG_Reservation calendar}',
        requireAuth: false,
        requireGuest: false,
        // Guests may open the calendar only when booking_calendar_guest is on
        beforeEnter: async (params, current, authManager) => {
            if (authManager?.isAuthenticated?.()) {
                return true;
            }
            return await isBookingCalendarGuest() ? true : '/login';
        }
    });
});

/**
 * Whether guests may view the reservation calendar (config booking_calendar_guest).
 * The production bundle starts AppConfigManager after RouterManager, so on the
 * first page load its config is not loaded yet and is fetched here instead.
 *
 * @returns {Promise<boolean>}
 */
async function isBookingCalendarGuest() {
    let cfg = window.AppConfigManager?.state?.lastConfig;
    if (!cfg) {
        try {
            const response = await ApiService.get('api/index/config/frontend-settings');
            cfg = response?.data?.data;
        } catch (error) {
            cfg = null;
        }
    }
    return !!cfg?.booking_calendar_guest;
}

function formatRoomWithImage(cell, rawValue, rowData, attributes) {
    const opts = attributes.lookupOptions || attributes.tableDataOptions || attributes.tableFilterOptions;

    // Normalizer: build a map value->text
    const makeMap = (options) => {
        if (!options) return new Map();
        if (Array.isArray(options)) {
            // [{value,text}, ...]
            return new Map(options.map(o => [String(o.value), o.text]));
        }
        // object map {val: label, ...}
        return new Map(Object.entries(options).map(([k, v]) => [String(k), v]));
    };

    const map = makeMap(opts);

    const key = rawValue === null || rawValue === undefined ? '' : String(rawValue);
    const label = map.has(key) ? map.get(key) : (rawValue && rawValue.text) ? rawValue.text : key;
    const roomNumber = rowData?.room_number ? `<small>${Utils.string.escape(rowData.room_number)}</small>` : '';

    const thumbHtml = rowData?.first_image_url
        ? `<span class="booking-table-thumb"><img src="${Utils.string.escape(rowData.first_image_url)}" alt="${Utils.string.escape(label || 'Room')}" loading="lazy"></span>`
        : '<span class="booking-table-thumb booking-table-thumb--placeholder icon-office" aria-hidden="true"></span>';

    cell.innerHTML =
        `<span class="booking-table-cell">${thumbHtml}<span class="booking-table-label"><strong>${Utils.string.escape(label || '-')}</strong>${roomNumber}</span></span>`;
}

function initBookingSettings(element, data) {
    const approveLevel = element.querySelector('#booking_approve_level');
    if (!approveLevel) {
        return () => {};
    }

    const approveChange = () => {
        element.querySelectorAll('.can-approve').forEach(el => {
            const level = parseInt(el.dataset.level || '0', 10);
            el.style.display = level > 0 && level <= parseInt(approveLevel.value || '0', 10) ? 'flex' : 'none';
        });
    };
    approveLevel.addEventListener('change', approveChange);
    approveChange();

    return () => {
        approveLevel.removeEventListener('change', approveChange);
    };
}

// Load application components after framework initialization
Utils.dom.loadResources([
    `Now/dist/eventcalendar.min.js`,
    `js/components/GallerySlideshow.js`,
    `Now/dist/eventcalendar.min.css`,
    `Now/css/galleryslideshow.css`
]);