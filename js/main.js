/**
 * Main Application
 * Now.js Framework
 */
document.addEventListener('DOMContentLoaded', async () => {
  try {
    // Detect current directory path
    const currentPath = window.location.pathname;
    const currentDir = currentPath.substring(0, currentPath.lastIndexOf('/') + 1);

    // Initialize framework
    await Now.init({
      // Environment mode: 'development' or 'production'
      environment: 'production',

      // Path configuration for templates and resources
      paths: {
        components: `${currentDir}components`,
        plugins: `${currentDir}plugins`,
        templates: `${currentDir}templates`,
        translations: `${currentDir}language`
      },

      // Enable framework-level auth so AuthManager will initialize before RouterManager
      auth: {
        enabled: true,
        autoInit: true,
        endpoints: {
          verify: 'api/index/auth/verify', // Used to check if the Token/Cookie sent by Client (such as Authorization Header or Cookie) is still correct/not expired or not.
          me: 'api/index/auth/me', // Restore the current user (Profile)
          login: 'api/index/auth/login', // Get a Creitedial (Email/Password) and reply token/session + user info.
          logout: 'api/index/auth/logout', // Cancel session /invalidates token at Server
          refresh: 'api/index/auth/refresh' // Used to ask for a new Token when the TOKEN is currently expired (if using JWT or Token-based Author)
        },

        token: {
          storageKey: 'auth_user'
        },

        redirects: {
          afterLogin: '/',
          afterLogout: '/login',
          unauthorized: '/login',
          forbidden: '/403'
        }
      },

      // Security configuration (CSRF token endpoint configurable here)
      security: {
        csrf: {
          enabled: true,
          tokenName: '_token',
          headerName: 'X-CSRF-Token',
          cookieName: 'XSRF-TOKEN',
          metaName: 'csrf-token',
          tokenUrl: 'api/index/auth/csrf-token' // CSRF endpoin
        }
      },

      // Internationalization settings
      i18n: {
        enabled: true,
        availableLocales: ['en', 'th']
      },

      // Application Configuration (Theme + Site Metadata)
      config: {
        enabled: true,
        defaultTheme: 'light',
        storageKey: 'crm_theme',
        systemPreference: false, // Not use system color scheme preference

        // Smooth transitions
        transition: {
          enabled: true,
          duration: 300,
          hideOnSwitch: true
        },

        // API config - auto-load theme + site metadata from server on init
        api: {
          enabled: true,
          configUrl: `api/index/config/frontend-settings`,  // Returns { variables, site }
          cacheResponse: true
        }
      },

      router: {
        enabled: true,
        base: currentDir,
        mode: 'history', // 'hash' or 'history'

        // Auth Configuration for Router
        auth: {
          enabled: true,
          autoGuard: true,
          defaultRequireAuth: true,
          publicPaths: ['/login', '/404'],
          guestOnlyPaths: ['/login'],
          redirects: {
            unauthenticated: '/login',
            unauthorized: '/login',
            forbidden: '/403',
            afterLogin: '/',
            afterLogout: '/login'
          }
        },

        notFound: {
          behavior: 'render',
          template: '404.html',
          title: 'Page Not Found'
        },

        routes: {
          '/': {
            template: 'index.html',
            title: '{LNG_Dashboard}',
            requireGuest: false,
            requireAuth: true
          },
          '/login': {
            template: 'login.html',
            title: '{LNG_Login}',
            requireGuest: true,
            requireAuth: false
          },
          '/forgot': {
            template: 'forgot.html',
            title: '{LNG_Forgot Password}',
            requireGuest: true,
            requireAuth: false
          },
          '/register': {
            template: 'register.html',
            title: '{LNG_Register}',
            requireGuest: true,
            requireAuth: false
          },
          '/reset-password': {
            template: 'reset-password.html',
            title: '{LNG_Reset Password}',
            requireGuest: true,
            requireAuth: false
          },
          '/activate': {
            template: 'activate.html',
            title: '{LNG_Activate Account}',
            requireGuest: true,
            requireAuth: false
          },
          '/logout': {
            requireAuth: false,
            beforeEnter: async (params, current, authManager) => {
              await authManager.logout();
              return '/login';
            }
          },
          '/profile': {
            template: 'profile.html',
            title: '{LNG_Edit Profile}',
            menuPath: '/users',
            requireAuth: true
          },
          '/profile': {
            template: 'profile.html',
            title: '{LNG_Profile}',
            menuPath: '/users',
            requireAuth: true
          },
          '/users': {
            template: 'users.html',
            title: '{LNG_Users}',
            requireAuth: true
          },
          '/categories': {
            template: 'settings/categories.html',
            title: '{LNG_Category}',
            requireAuth: true
          },
          '/user-status': {
            template: 'settings/userstatus.html',
            title: '{LNG_Member status}',
            requireAuth: true
          },
          '/permission': {
            template: 'settings/permission.html',
            title: '{LNG_Permissions}',
            requireAuth: true
          },
          '/general-settings': {
            template: 'settings/general.html',
            title: '{LNG_General Settings}',
            requireAuth: true
          },
          '/company-settings': {
            template: 'settings/company.html',
            title: '{LNG_Company Settings}',
            requireAuth: true
          },
          '/email-settings': {
            template: 'settings/email.html',
            title: '{LNG_Email Settings}',
            requireAuth: true
          },
          '/api-settings': {
            template: 'settings/api.html',
            title: '{LNG_API Settings}',
            requireAuth: true
          },
          '/theme-settings': {
            template: 'settings/theme.html',
            title: '{LNG_Theme Settings}',
            requireAuth: true
          },
          '/line-settings': {
            template: 'settings/line.html',
            title: '{LNG_Line Settings}',
            requireAuth: true
          },
          '/telegram-settings': {
            template: 'settings/telegram.html',
            title: '{LNG_Telegram Settings}',
            requireAuth: true
          },
          '/sms-settings': {
            template: 'settings/sms.html',
            title: '{LNG_SMS Settings}',
            requireAuth: true
          },
          '/cookie-policy': {
            template: 'settings/cookie-policy.html',
            title: '{LNG_Cookie Policy}',
            requireAuth: true
          },
          '/languages': {
            template: 'settings/languages.html',
            title: '{LNG_Manage languages}',
            requireAuth: true
          },
          '/language': {
            template: 'settings/language.html',
            title: '{LNG_Add}/{LNG_Edit} {LNG_Language}',
            menuPath: '/languages',
            requireAuth: true
          },
          '/usage': {
            template: 'settings/usage.html',
            title: '{LNG_Usage history}',
            requireAuth: true
          },
          '/403': {
            template: '403.html',
            title: '{LNG_Access Denied}',
            requireAuth: true
          },
          '/404': {
            template: '404.html',
            title: '{LNG_Page Not Found}'
          }
        }
      },

      scroll: {
        enabled: false,
        selectors: {
          content: '.content',
        }
      }
    }).then(() => {
      // Load application components after framework initialization
      const scripts = [
        `${currentDir}js/components/sidebar.js`,
        `${currentDir}js/components/topbar.js`,
        `${currentDir}js/components/SocialLogin.js`
      ];

      // Dynamically load all component scripts
      scripts.forEach(src => {
        const script = document.createElement('script');
        script.src = src;
        document.head.appendChild(script);
      });
    });

    // Create application instance
    const app = await Now.createApp({
      name: 'Now.js',
      version: '1.0.0'
    });

  } catch (error) {
    console.error('Application initialization failed:', error);
  }
});

function initProfile(element, data) {
  const input = element.querySelector('#birthday');
  const display = element.querySelector('.dropdown-display');

  const updateAge = () => {
    if (input.value) {
      const birth = new Date(input.value);
      const age = Math.floor((Date.now() - birth) / 31557600000);

      // Format date with standard pattern (YYYY uses locale-based year: BE for Thai, CE for others)
      const formattedDate = Utils.date.format(input.value, 'D MMMM YYYY');

      display.textContent = `${formattedDate} (${age} ${Now.translate('years')})`;
    } else {
      display.textContent = '';
    }
  };

  input.addEventListener('change', updateAge);
  updateAge();

  // Return cleanup function (optional)
  return () => {
    input.removeEventListener('change', updateAge);
  };
}

function initGeneralSettings(element, data) {
  const timezone = element.querySelector('#timezone');
  const server_time = element.querySelector('#server_time');
  const local_time = element.querySelector('#local_time');
  let intervalId = 0;

  const updateTimes = () => {
    // Update local time with selected timezone
    if (local_time && timezone?.value) {
      const options = {
        timeZone: timezone.value,
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
      };
      local_time.textContent = new Date().toLocaleString('en-GB', options).replace(',', '');
    }

    // Update server time (add elapsed time to initial server time)
    if (server_time) {
      // Parse d/m/Y H:i:s format
      const parts = server_time.textContent.match(/(\d+)\/(\d+)\/(\d+)\s+(\d+):(\d+):(\d+)/);
      if (parts) {
        const seconds = parseInt(parts[6]) + 1;
        const serverStartTime = new Date(parts[3], parts[2] - 1, parts[1], parts[4], parts[5], seconds);
        const currentServerTime = new Date(serverStartTime.getTime());
        server_time.textContent = Utils.date.format(currentServerTime, 'DD/MM/YYYY HH:mm:ss', 'th-CE');
      }

    }
  };

  if (timezone && server_time && local_time) {
    updateTimes();
    intervalId = window.setInterval(updateTimes, 1000);
  }

  // Return cleanup function (optional)
  return () => {
    window.clearInterval(intervalId);
  };
}

function initEmailSettings(element, data) {
  const email_SMTPAuth = element.querySelector('#email_SMTPAuth');
  const test_email = element.querySelector('#test_email');

  const smtpAuthChange = () => {
    element.querySelector('#email_SMTPSecure').disabled = !email_SMTPAuth.checked;
    element.querySelector('#email_Username').disabled = !email_SMTPAuth.checked;
    element.querySelector('#email_Password').disabled = !email_SMTPAuth.checked;
  };
  email_SMTPAuth.addEventListener('change', smtpAuthChange);
  smtpAuthChange();

  // Test email button handler - sends to logged-in user's email
  const testEmailClick = async () => {
    // Disable button during request
    test_email.disabled = true;
    const originalText = test_email.innerHTML;
    test_email.innerHTML = '<span class="spinner"></span> ' + Now.translate('Sending...');

    try {
      const response = await ApiService.post('api/index/settings/testEmail');

      if (response.success) {
        NotificationManager.success(response.message || Now.translate('Test sent successfully'));
      } else {
        NotificationManager.error(response.message || Now.translate('Failed to send test'));
      }
    } catch (error) {
      NotificationManager.error(Now.translate('Failed to send test'));
    } finally {
      test_email.disabled = false;
      test_email.innerHTML = originalText;
    }
  };

  if (test_email) {
    test_email.addEventListener('click', testEmailClick);
  }

  // Return cleanup function
  return () => {
    email_SMTPAuth.removeEventListener('change', smtpAuthChange);
    if (test_email) {
      test_email.removeEventListener('click', testEmailClick);
    }
  };
}

function initForbiddenPage(element, data) {
  const messageElement = element.querySelector('[data-forbidden-message]');
  if (!messageElement) {
    return;
  }

  const message = data?.query?.message;

  if (typeof message === 'string' && message.trim() !== '') {
    messageElement.textContent = message.trim();
    messageElement.hidden = false;
    return;
  }

  messageElement.textContent = '';
  messageElement.hidden = true;
}

/**
 * Format with options status
 */
function formatTableOptionStatus(cell, rawValue, rowData, attributes) {
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
  const index = map.has(key) ? Array.from(map.keys()).indexOf(key) : -1;


  cell.innerHTML = `<span class="status${index}" data-i18n>${label}</span>`;
}

function formatStarStatus(cell, rawValue, rowData, attributes) {
  if (rawValue === 'active' || parseInt(rawValue) === 1) {
    cell.innerHTML = '<span class="icon-star2 color-primary"></span>';
  } else {
    cell.innerHTML = '<span class="icon-star0 color-silver"></span>';
  }
}

function formatActiveStatus(cell, rawValue, rowData, attributes) {
  if (rawValue === 'active' || parseInt(rawValue) === 1) {
    cell.innerHTML = '<span class="icon-valid color-red" title="' + Now.translate('Active') + '"></span>';
  } else {
    cell.innerHTML = '<span class="icon-invalid color-silver" title="' + Now.translate('Inactive') + '"></span>';
  }
}

function formatLink(cell, rawValue, rowData, attributes) {
  if (!rawValue) {
    cell.innerHTML = '-';
    return;
  }

  const value = String(rawValue).trim();

  // Simple recognizers
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  const phoneRegex = /^\+?[0-9()\s\-./]{6,}$/;
  const urlProtocolRegex = /^https?:\/\//i;

  const makeLink = (href, text, iconClass) => {
    const a = document.createElement('a');
    a.href = href;
    // Open http(s) links in new tab, others (mailto/tel) in same
    if (/^https?:\/\//i.test(href)) {
      a.target = '_blank';
      a.rel = 'noopener';
    }
    if (iconClass) a.className = iconClass;
    a.textContent = text;
    cell.innerHTML = '';
    cell.appendChild(a);
  };

  if (/^mailto:/i.test(value)) {
    makeLink(value, value.replace(/^mailto:/i, ''), 'icon-mail');
    return;
  }

  if (/^tel:/i.test(value)) {
    makeLink(value, value.replace(/^tel:/i, ''), 'icon-phone');
    return;
  }

  if (emailRegex.test(value)) {
    makeLink('mailto:' + value, value, 'icon-mail');
    return;
  }

  if (phoneRegex.test(value)) {
    // Normalize phone for href (keep leading + if present)
    const telHref = 'tel:' + value.replace(/[^\d+]/g, '');
    makeLink(telHref, value, 'icon-phone');
    return;
  }

  // Fallback: treat as URL
  let href = value;
  if (!urlProtocolRegex.test(href)) href = 'http://' + href;
  const displayUrl = href.replace(/^https?:\/\//, '').replace(/\/$/, '');
  makeLink(href, displayUrl, 'icon-world');
}

function formatImage(cell, rawValue, rowData, attributes) {
  if (rawValue) {
    cell.innerHTML = '<div class="thumbnail" style="background-image: url(' + rawValue + ')"></div>';
  } else {
    cell.innerHTML = '';
  }
}

function copyToClipboard(cell, rawValue, rowData, attributes) {
  if (rawValue) {
    const link = document.createElement('a');
    link.className = 'icon-copy';
    link.textContent = rawValue;
    link.style.cursor = 'pointer';
    link.addEventListener('click', () => Utils.dom.copyToClipboard(String(rawValue)));
    cell.innerHTML = '';
    cell.appendChild(link);
  } else {
    cell.textContent = '';
  }
}
