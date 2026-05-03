/**
 * PasteCleanerPlugin - Clean paste: strip all formatting, keep only text
 * Preserves line breaks (<br>), tables, and iframes.
 * Removes all class and id attributes.
 *
 * When active, every paste operation is intercepted and cleaned.
 * Toggle via toolbar button or Ctrl+Shift+V shortcut (always available via ContentArea).
 *
 * @author Goragod Wiriya
 * @version 1.0
 */
import PluginBase from '../PluginBase.js';
import EventBus from '../../core/EventBus.js';

class PasteCleanerPlugin extends PluginBase {
  static pluginName = 'pasteCleaner';

  /** Tags whose **structure** we keep (content is preserved for all tags). */
  static ALLOWED_TAGS = new Set([
    // text-level
    'br', 'p', 'div',
    // table
    'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'colgroup', 'col',
    // iframe / embedded
    'iframe',
    // lists (structural, often needed)
    'ul', 'ol', 'li',
    // basic semantics
    'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    'blockquote', 'pre', 'code', 'hr'
  ]);

  init() {
    super.init();

    this.isActive = false;

    // Merge user-provided allow list
    const extra = this.options.allowTags || [];
    this._allowedTags = new Set([...PasteCleanerPlugin.ALLOWED_TAGS, ...extra.map(t => t.toLowerCase())]);

    // Listen for toolbar button
    this.subscribe(EventBus.Events.TOOLBAR_BUTTON_CLICK, (event) => {
      if (event.id === 'pasteCleaner') {
        this.toggle();
      }
    });

    // Register command so toolbar can query isActive
    this.registerCommand('pasteCleaner', {
      execute: () => this.toggle(),
      isActive: () => this.isActive
    });

    // Intercept paste when active
    this.subscribe(EventBus.Events.CONTENT_PASTE, (event) => {
      if (this.isActive) {
        this.handlePaste(event);
      }
    });
  }

  /**
   * Toggle clean-paste mode
   * @param {boolean} [force] - Force on/off
   */
  toggle(force) {
    this.isActive = typeof force === 'boolean' ? force : !this.isActive;
    this.setButtonActive('pasteCleaner', this.isActive);

    const key = this.isActive ? 'Clean paste ON' : 'Clean paste OFF';
    this.notify(this.translate(key), 'info');
  }

  /**
   * Intercept paste and clean HTML
   * @param {ClipboardEvent} event
   */
  handlePaste(event) {
    const clipboardData = event.clipboardData || window.clipboardData;
    if (!clipboardData) return;

    // Skip if Ctrl+Shift+V — ContentArea already handles that as plain text
    if (event.shiftKey && (event.ctrlKey || event.metaKey)) return;

    event.preventDefault();

    const html = clipboardData.getData('text/html');
    const text = clipboardData.getData('text/plain');

    let cleaned;
    if (html) {
      cleaned = this.cleanHtml(html);
    } else if (text) {
      // Plain text — escape and convert newlines to <br>
      cleaned = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\n/g, '<br>');
    }

    if (cleaned) {
      this.editor.selection?.insertHtml(cleaned);
      this.emit(EventBus.Events.CONTENT_CHANGE);
    }
  }

  /**
   * Strip all formatting from HTML, keeping only allowed structural tags.
   * All class / id attributes are removed. Inline styles are removed.
   * @param {string} html
   * @returns {string}
   */
  cleanHtml(html) {
    const temp = document.createElement('div');
    temp.innerHTML = html;

    // Remove <script>, <style>, <meta>, <link>, <title>, <head> etc
    temp.querySelectorAll('script, style, meta, link, title, head, noscript, object, embed, applet')
      .forEach(el => el.remove());

    // Remove HTML comments
    const walker = document.createTreeWalker(temp, NodeFilter.SHOW_COMMENT);
    const comments = [];
    while (walker.nextNode()) {
      comments.push(walker.currentNode);
    }
    comments.forEach(c => c.parentNode?.removeChild(c));

    // Walk the tree: unwrap disallowed tags, strip attributes from allowed ones
    this._walkAndClean(temp);

    // Collapse multiple consecutive <br>
    let result = temp.innerHTML;
    result = result.replace(/(<br\s*\/?>[\s]*){3,}/gi, '<br><br>');

    return result.trim();
  }

  /**
   * Recursively clean an element tree in-place.
   * @param {Node} root
   */
  _walkAndClean(root) {
    // Process children in reverse so removals don't shift indices
    const children = Array.from(root.childNodes);
    for (const child of children) {
      if (child.nodeType === Node.ELEMENT_NODE) {
        const tag = child.tagName.toLowerCase();

        if (this._allowedTags.has(tag)) {
          // Keep the element but strip all attributes except essential ones
          this._stripAttributes(child, tag);
          // Recurse into children
          this._walkAndClean(child);
        } else {
          // Unwrap: replace element with its children
          this._walkAndClean(child); // clean children first
          while (child.firstChild) {
            root.insertBefore(child.firstChild, child);
          }
          root.removeChild(child);
        }
      }
      // Text nodes and other node types are left as-is
    }
  }

  /**
   * Remove all attributes from an element.
   * For iframes, keep src, width, height, allowfullscreen, frameborder.
   * For tables/td/th, keep colspan, rowspan.
   * @param {HTMLElement} el
   * @param {string} tag
   */
  _stripAttributes(el, tag) {
    // Attributes we keep per tag
    const keep = new Set();
    if (tag === 'iframe') {
      keep.add('src');
      keep.add('width');
      keep.add('height');
      keep.add('allowfullscreen');
      keep.add('frameborder');
      keep.add('allow');
    }
    if (tag === 'td' || tag === 'th') {
      keep.add('colspan');
      keep.add('rowspan');
    }
    if (tag === 'col' || tag === 'colgroup') {
      keep.add('span');
    }
    if (tag === 'ol') {
      keep.add('start');
      keep.add('type');
    }

    const attrs = Array.from(el.attributes);
    for (const attr of attrs) {
      if (!keep.has(attr.name)) {
        el.removeAttribute(attr.name);
      }
    }
  }

  destroy() {
    super.destroy();
  }
}

export default PasteCleanerPlugin;
