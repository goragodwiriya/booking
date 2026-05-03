/**
 * Custom Color Picker Component - No Native Input Dependencies
 * Creates a complete color picker UI with dropdown functionality
 */

// Predefined color palette
const COLOR_PALETTE = [
  '#FF0000', '#00FF00', '#0000FF', '#FFFF00', '#FF00FF', '#00FFFF',
  '#FFA500', '#800080', '#008000', '#800000', '#000080', '#808000',
  '#008080', '#808080', '#A52A2A', '#FA8072', '#4682B4', '#D2691E',
  '#2E8B57', '#FFD700', '#DA70D6', '#C71585', '#40E0D0', '#F0E68C',
  '#ADD8E6', '#90EE90', '#FF69B4', '#B0C4DE', '#EEE8AA', '#98FB98',
  '#F5F5DC', '#FFE4E1', '#F08080', '#E0FFFF', '#D8BFD8', '#F4A460',
  '#BC8F8F', '#CD5C5C', '#6B8E23', '#556B2F', '#8FBC8F', '#483D8B',
  '#B8860B', '#A9A9A9', '#3CB371', '#BA55D3', '#9370DB', '#66CDAA',
  '#7B68EE', '#708090', '#FFFFFF', '#000000'
];

/**
 * Enhanced Color Picker implementation
 */
class EmbeddedColorPicker {
  constructor(originalElement) {
    this._original = originalElement;
    if (!this._original) {
      throw new Error('ColorPicker: Original element is required');
    }

    this.name = this._original.getAttribute('name') || '';
    this.id = this._original.id || ('colorpicker-' + Math.random().toString(36).slice(2, 8));

    // Initialize state
    this.selectedColor = '';
    this.isOpen = false;
    this.disabled = this._original.hasAttribute('disabled');
    this.readonly = this._original.hasAttribute('readonly');

    // Set initial value if exists
    const initialValue = this._original.getAttribute('value');
    const currentValue = this._original.value;

    this._initializeElements();
    this._setupEventListeners();
    this._replaceOriginalElement();

    // Only set color if there's an explicit value attribute or non-default value
    if (initialValue && initialValue !== '') {
      this.setColor(initialValue);
    } else if (currentValue && currentValue !== '' && this._original.hasAttribute('value')) {
      this.setColor(currentValue);
    } else {
      // Clear any default value - leave empty
      this.selectedColor = '';
      window.setTimeout(() => {
        this.hiddenInput.value = '';
        this.hiddenInput.removeAttribute('value');
      }, 0);
    }
  }

  _initializeElements() {
    // Create main wrapper
    const existingFormControl = this._original.closest('.form-control');
    if (existingFormControl) {
      this.wrapper = existingFormControl;
    } else {
      this.wrapper = document.createElement('div');
    }
    this.wrapper.classList.add('custom-colorpicker');
    this.wrapper.id = this.id + '_wrapper';

    // Create display button
    this.displayButton = document.createElement('button');
    this.displayButton.type = 'button';
    this.displayButton.className = 'dropdown-button';
    this.displayButton.innerHTML = `
      <span class="dropdown-display">${Now.translate(this._original.placeholder || 'Choose a color')}</span>
      <span class="dropdown-arrow"></span>
    `;
    this.wrapper.appendChild(this.displayButton);

    // Get references to child elements
    this.colorText = this.displayButton.querySelector('.dropdown-display');

    this.hiddenInput = null;

    // Create color picker dropdown (will be moved to DropdownPanel)
    this.dropdown = document.createElement('div');
    this.dropdown.className = 'colorpicker-dropdown';

    // Get DropdownPanel singleton
    this.dropdownPanel = DropdownPanel.getInstance();

    this._createColorPalette();
  }

  _createColorPalette() {
    // Create color input section
    const inputSection = document.createElement('div');
    inputSection.className = 'color-input-section';

    const hexInput = document.createElement('input');
    hexInput.type = 'text';
    hexInput.className = 'hex-input';
    hexInput.placeholder = '#000000';
    hexInput.maxLength = 7;
    this.hexInput = hexInput;

    const clearButton = document.createElement('button');
    clearButton.type = 'button';
    clearButton.className = 'clear-color-btn';
    clearButton.textContent = Now.translate('Clear');

    inputSection.appendChild(hexInput);
    inputSection.appendChild(clearButton);
    this.dropdown.appendChild(inputSection);

    // Create palette sections
    this._createPaletteSection('Basic Colors', COLOR_PALETTE, 'basic-colors');

    // Setup input section events
    hexInput.addEventListener('input', (e) => {
      let value = e.target.value;
      if (!value.startsWith('#') && value.length > 0) {
        value = '#' + value;
        e.target.value = value;
      }

      if (this._isValidColor(value)) {
        this._previewColor(value);
      }
    });

    hexInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        const color = e.target.value;
        if (this._isValidColor(color)) {
          this.selectColor(color);
        }
      }
    });

    // Select all text on focus
    hexInput.addEventListener('focus', (e) => {
      e.target.select();
    });

    // Copy/Paste support for hex input
    hexInput.addEventListener('paste', (e) => {
      e.stopPropagation(); // Prevent triggering dropdown paste event
      setTimeout(() => {
        let value = e.target.value.trim();
        // Add # if missing
        if (value && !value.startsWith('#') && /^[A-Fa-f0-9]{3,6}$/.test(value)) {
          value = '#' + value;
          e.target.value = value;
        }
        if (this._isValidColor(value)) {
          this._previewColor(value);
        }
      }, 0);
    });

    clearButton.addEventListener('click', (e) => {
      e.preventDefault();
      this.selectColor('');
    });
  }

  _createPaletteSection(title, colors, className) {
    const section = document.createElement('div');
    section.className = `palette-section ${className}`;

    const palette = document.createElement('div');
    palette.className = 'color-palette';

    colors.forEach((color, index) => {
      const colorButton = document.createElement('button');
      colorButton.type = 'button';
      colorButton.className = 'color-swatch';
      colorButton.style.backgroundColor = color;
      colorButton.title = color;
      colorButton.setAttribute('data-color', color);

      // Add accessibility
      colorButton.setAttribute('aria-label', `Select color ${color}`);

      colorButton.addEventListener('click', (e) => {
        e.preventDefault();
        this.selectColor(color);
      });

      colorButton.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          this.selectColor(color);
        }
      });

      palette.appendChild(colorButton);
    });

    section.appendChild(palette);
    this.dropdown.appendChild(section);
  }

  _setupEventListeners() {
    // Display button click
    this.displayButton.addEventListener('click', (e) => {
      e.preventDefault();
      if (this.disabled || this.readonly) return;
      this.toggle();
    });

    // Keyboard navigation
    this.wrapper.addEventListener('keydown', (e) => {
      if (this.disabled || this.readonly) return;

      // Don't intercept backspace when typing inside inputs
      const target = e.target;
      if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable)) {
        return;
      }

      switch (e.key) {
        case 'Backspace':
          if (this.hiddenInput?.value) {
            e.preventDefault();
            this.selectColor('');
          }
          break;
        case 'Enter':
        case ' ':
          e.preventDefault();
          this.toggle();
          break;
        case 'Escape':
          e.preventDefault();
          this.close();
          break;
      }
    });

    // Copy/Paste support - works on wrapper, button, and dropdown
    const setupCopyPaste = (element) => {
      element.addEventListener('copy', (e) => {
        if (this.hiddenInput.value) {
          e.preventDefault();
          e.stopPropagation();
          e.clipboardData.setData('text/plain', this.hiddenInput.value);
        }
      });

      element.addEventListener('paste', (e) => {
        if (this.disabled || this.readonly) return;
        e.preventDefault();
        e.stopPropagation();
        let pastedText = e.clipboardData.getData('text/plain').trim();

        // Add # if missing
        if (pastedText && !pastedText.startsWith('#') && /^[A-Fa-f0-9]{3,6}$/.test(pastedText)) {
          pastedText = '#' + pastedText;
        }

        if (pastedText && this._isValidColor(pastedText)) {
          this.setColor(pastedText);
          // Close dropdown after paste
          if (this.isOpen) {
            this.close();
          }
        }
      });
    };

    setupCopyPaste(this.wrapper);
    setupCopyPaste(this.displayButton);
    setupCopyPaste(this.dropdown);
  }

  _isValidColor(color) {
    if (!color) return true; // Empty is valid (means no color)

    // Check hex color format
    const hexPattern = /^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/;
    return hexPattern.test(color);
  }

  _previewColor(color) {
    if (color) {
      this.wrapper.style.backgroundColor = color;
      this.wrapper.style.color = this._getContrastColor(color);
      this.colorText.textContent = color.toUpperCase();
    }
  }

  _getContrastColor(hexColor) {
    // Convert hex to RGB
    const r = parseInt(hexColor.slice(1, 3), 16);
    const g = parseInt(hexColor.slice(3, 5), 16);
    const b = parseInt(hexColor.slice(5, 7), 16);

    // Calculate luminance
    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;

    return luminance > 0.5 ? '#000000' : '#FFFFFF';
  }

  // Public methods
  open() {
    if (this.disabled || this.readonly || this.isOpen) return;

    this.isOpen = true;
    this.wrapper.classList.add('open');

    // Update hex input with current color
    if (this.selectedColor) {
      this.hexInput.value = this.selectedColor;
    } else {
      this.hexInput.value = '';
    }

    // Show dropdown in DropdownPanel
    this.dropdownPanel.show(this.wrapper, this.dropdown, {
      align: 'left',
      offsetY: 5,
      onClose: () => {
        this.isOpen = false;
        this.wrapper.classList.remove('open');
        // Dispatch close event
        this.wrapper.dispatchEvent(new CustomEvent('colorpicker:close', {
          detail: {color: this.selectedColor}
        }));
      }
    });

    // Focus hex input after panel is shown and select all text
    if (this.hexInput) {
      setTimeout(() => {
        this.hexInput.focus();
        this.hexInput.select();
      }, 50);
    }

    // Dispatch event
    this.wrapper.dispatchEvent(new CustomEvent('colorpicker:open', {
      detail: {color: this.selectedColor}
    }));
  }

  close() {
    if (!this.isOpen) return;

    // Hide the DropdownPanel (will trigger onClose callback)
    this.dropdownPanel.hide();
  }

  toggle() {
    if (this.isOpen) {
      this.close();
    } else {
      this.open();
    }
  }

  selectColor(color) {
    this.selectedColor = color;
    this.hiddenInput.value = color;
    this.hiddenInput.setAttribute('value', color);

    if (color) {
      this.wrapper.style.backgroundColor = color;
      this.wrapper.style.color = this._getContrastColor(color);
      this.colorText.textContent = color.toUpperCase();
      this.colorText.style.color = this._getContrastColor(color);
    } else {
      this.wrapper.style.backgroundColor = '';
      this.wrapper.style.color = '';
      this.colorText.textContent = Now.translate(this._original.placeholder || 'Choose a color');
      this.colorText.style.color = '';
    }

    // Update selected state in palette
    this.dropdown.querySelectorAll('.color-swatch').forEach(swatch => {
      swatch.classList.remove('selected');
      if (swatch.getAttribute('data-color') === color) {
        swatch.classList.add('selected');
      }
    });

    this.close();

    // Focus to wrapper
    setTimeout(() => {
      this.wrapper.focus();
    }, 0);

    // Dispatch events
    const changeEvent = new Event('change', {bubbles: true});
    this.hiddenInput.dispatchEvent(changeEvent);

    this.wrapper.dispatchEvent(new CustomEvent('colorpicker:change', {
      detail: {
        color: color,
        rgb: color ? this._hexToRgb(color) : null
      }
    }));
  }

  _hexToRgb(hex) {
    if (!hex) return null;

    const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return result ? {
      r: parseInt(result[1], 16),
      g: parseInt(result[2], 16),
      b: parseInt(result[3], 16)
    } : null;
  }

  setColor(color) {
    if (this._isValidColor(color)) {
      this.selectColor(color);
    }
  }

  getColor() {
    return this.hiddenInput.value || null;
  }

  setDisabled(disabled) {
    this.disabled = disabled;
    this.wrapper.classList.toggle('disabled', disabled);
    this.displayButton.disabled = disabled;
    if (disabled) {
      this.close();
    }
  }

  setReadonly(readonly) {
    this.readonly = readonly;
    this.wrapper.classList.toggle('readonly', readonly);
    if (readonly) {
      this.close();
    }
  }

  destroy() {
    if (this.wrapper && this.wrapper.parentNode) {
      this.wrapper.parentNode.removeChild(this.wrapper);
    }
  }

  getElement() {
    return this.wrapper;
  }

  _replaceOriginalElement() {
    if (this._original.parentNode) {
      // Hide original element but keep it in the DOM for form submission
      this._original.type = 'hidden';
      this._original.style.display = 'none';

      // Use original element as hiddenInput
      this.hiddenInput = this._original;

      // Insert wrapper before original element only if wrapper is not already the parent
      if (this._original.parentNode !== this.wrapper) {
        this._original.parentNode.insertBefore(this.wrapper, this._original);
      }
    }
  }
}

// Expose globally
window.EmbeddedColorPicker = EmbeddedColorPicker;

/**
 * ColorElementFactory - Factory for creating custom color picker elements
 */
class ColorElementFactory extends ElementFactory {
  static config = {
    ...ElementFactory.config,
    type: 'color'
  };

  static createInstance(element, config = {}) {
    const instance = super.createInstance(element, config);
    return instance;
  }

  static setupElement(instance) {
    const {element} = instance;

    try {
      const colorPicker = new EmbeddedColorPicker(element);

      instance.wrapper = colorPicker.getElement();
      instance.colorPicker = colorPicker;

      // Bind methods
      instance.getColor = () => colorPicker.getColor();
      instance.setColor = (color) => colorPicker.setColor(color);
      instance.setValue = (color) => colorPicker.setColor(color); // Alias for FormManager compatibility
      instance.getValue = () => colorPicker.getColor(); // Alias for consistency
      instance.open = () => colorPicker.open();
      instance.close = () => colorPicker.close();
      instance.toggle = () => colorPicker.toggle();
      instance.setDisabled = (disabled) => colorPicker.setDisabled(disabled);
      instance.setReadonly = (readonly) => colorPicker.setReadonly(readonly);

      // Update element reference
      element.wrapper = instance.wrapper;

      // Setup event forwarding
      if (colorPicker.hiddenInput) {
        colorPicker.hiddenInput.addEventListener('change', (e) => {
          // Prevent infinite loop: don't re-dispatch if event came from element itself
          if (e.target === element) return;

          // Dispatch standard change event
          const changeEvent = new Event('change', {bubbles: true});
          element.dispatchEvent(changeEvent);

          // Emit to event system
          EventManager.emit('element:change', {
            elementId: element.id,
            value: colorPicker.getColor(),
            type: 'color'
          });
        });
      }

      // Forward custom events
      colorPicker.wrapper.addEventListener('colorpicker:change', (e) => {
        element.dispatchEvent(new CustomEvent('colorpicker:change', {
          detail: e.detail
        }));
      });

      colorPicker.wrapper.addEventListener('colorpicker:open', (e) => {
        element.dispatchEvent(new CustomEvent('colorpicker:open', {
          detail: e.detail
        }));
      });

      colorPicker.wrapper.addEventListener('colorpicker:close', (e) => {
        element.dispatchEvent(new CustomEvent('colorpicker:close', {
          detail: e.detail
        }));
      });

      // Cleanup override
      const originalCleanup = instance.cleanup;
      instance.cleanup = function() {
        if (this.colorPicker) {
          this.colorPicker.destroy();
          this.colorPicker = null;
        }
        if (originalCleanup) {
          originalCleanup.call(this);
        }
        return this;
      };

      return instance;

    } catch (error) {
      console.error('ColorElementFactory: Failed to setup element:', error);
      return instance;
    }
  }
}

// Register with ElementManager
ElementManager.registerElement('color', ColorElementFactory);

// Expose globally
window.ColorElementFactory = ColorElementFactory;
