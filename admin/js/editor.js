(() => {
  const config = window.PureblogEditorConfig || {};
  const contentField = document.getElementById('content');
  if (!contentField || typeof window.CodeJar === 'undefined') {
    return;
  }

  const editorForm = document.getElementById(config.formId || '');
  const slugField = document.getElementById('slug');
  const statusField = document.getElementById('status');
  const titleField = document.getElementById('title');
  const descriptionField = document.getElementById('description');
  const dateField = document.getElementById('date');
  const tagsField = document.getElementById('tags');
  const previewButton = document.getElementById('preview-button');
  const scrollKeyBase = `${config.editorType || 'editor'}:${window.location.pathname}`;

  const uploadForm = document.querySelector('.upload-form');
  const uploadInput = uploadForm?.querySelector('input[type="file"]');
  const uploadButton = uploadForm?.querySelector('button[type="submit"]');
  let allowUploadSubmit = false;

  // Create main editor container for CodeJar
  const editorContainer = document.createElement('div');
  editorContainer.id = 'content-editor';
  editorContainer.className = 'editor language-markdown';
  editorContainer.contentEditable = 'true';
  editorContainer.spellcheck = true;
  editorContainer.textContent = contentField.value;

  contentField.style.display = 'none';
  contentField.parentNode.insertBefore(editorContainer, contentField.nextSibling);

  // Initialize CodeJar for the main editor
  const jar = window.CodeJar(editorContainer, (editor) => {
    Prism.highlightElement(editor);
    if (editor.textContent.endsWith('\n')) {
      editor.appendChild(document.createElement('br'));
    }
  }, { spellcheck: true, addClosing: false, preserveIdent: false });

  jar.onUpdate((code) => {
    contentField.value = code;
    scheduleAutosave();
  });

  // Handle Markdown shortcut keydown events for main editor
  editorContainer.addEventListener('keydown', (event) => {
    const isCmdOrCtrl = event.metaKey || event.ctrlKey;
    if (isCmdOrCtrl) {
      if (event.key.toLowerCase() === 'b') {
        event.preventDefault();
        wrapSelection(jar, editorContainer, '**');
      } else if (event.key.toLowerCase() === 'i') {
        event.preventDefault();
        wrapSelection(jar, editorContainer, '*');
      } else if (event.key.toLowerCase() === 'k') {
        event.preventDefault();
        insertLink(jar, editorContainer);
      }
    }
  });

  // Initialize CodeJar for layout markdown editors if any
  const layoutJars = [];
  document.querySelectorAll('textarea[data-layout-markdown]').forEach((textarea) => {
    const layoutContainer = document.createElement('div');
    layoutContainer.className = 'editor language-markdown';
    layoutContainer.contentEditable = 'true';
    layoutContainer.spellcheck = true;
    layoutContainer.textContent = textarea.value;

    textarea.style.display = 'none';
    textarea.parentNode.insertBefore(layoutContainer, textarea.nextSibling);

    const layoutJar = window.CodeJar(layoutContainer, (editor) => {
      Prism.highlightElement(editor);
      if (editor.textContent.endsWith('\n')) {
        editor.appendChild(document.createElement('br'));
      }
    }, { spellcheck: true, addClosing: false, preserveIdent: false });

    layoutJar.onUpdate((code) => {
      textarea.value = code;
      scheduleAutosave();
    });

    layoutContainer.addEventListener('keydown', (event) => {
      const isCmdOrCtrl = event.metaKey || event.ctrlKey;
      if (isCmdOrCtrl) {
        if (event.key.toLowerCase() === 'b') {
          event.preventDefault();
          wrapSelection(layoutJar, layoutContainer, '**');
        } else if (event.key.toLowerCase() === 'i') {
          event.preventDefault();
          wrapSelection(layoutJar, layoutContainer, '*');
        } else if (event.key.toLowerCase() === 'k') {
          event.preventDefault();
          insertLink(layoutJar, layoutContainer);
        }
      }
    });

    layoutJars.push({ jar: layoutJar, element: layoutContainer, textarea: textarea });
  });

  // selection wrapper using standard DOM APIs and execCommand (for undo support)
  function wrapSelection(targetJar, targetElement, wrapper) {
    targetElement.focus();
    const selection = window.getSelection();
    if (!selection.rangeCount) return;

    const range = selection.getRangeAt(0);
    const selectedText = range.toString();
    const newText = wrapper + selectedText + wrapper;

    document.execCommand('insertText', false, newText);
  }

  function insertLink(targetJar, targetElement) {
    targetElement.focus();
    const selection = window.getSelection();
    if (!selection.rangeCount) return;

    const range = selection.getRangeAt(0);
    const selectedText = range.toString();
    const newText = selectedText ? `[${selectedText}]()` : '[]()';

    document.execCommand('insertText', false, newText);

    // Place cursor inside parentheses if text was selected, or inside brackets if not
    const focusNode = selection.focusNode;
    const focusOffset = selection.focusOffset;
    if (focusNode && focusNode.nodeType === Node.TEXT_NODE) {
      if (selectedText) {
        selection.collapse(focusNode, focusOffset - 1);
      } else {
        selection.collapse(focusNode, focusOffset - 3);
      }
    }
  }

  function insertTextAtCursor(targetJar, targetElement, text) {
    targetElement.focus();
    const selection = window.getSelection();
    if (!selection.rangeCount) return;

    document.execCommand('insertText', false, text);
  }

  const autosaveStatus = document.getElementById('autosave-status');
  let autosaveTimer = null;

  function setAutosaveStatus(text) {
    if (autosaveStatus) autosaveStatus.textContent = text;
  }

  async function discardAutosave() {
    const slugValue = (slugField?.value ?? '').trim();
    if (slugValue === '') return;
    const formData = new FormData();
    formData.set('csrf_token', config.csrfToken);
    formData.set('action', 'discard');
    formData.set('slug', slugValue);
    formData.set('editor_type', config.editorType || 'post');
    await fetch((config.basePath || '') + '/admin/autosave.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
    }).catch(() => {});
  }

  async function doAutosave() {
    const slugValue = (slugField?.value ?? '').trim();
    if (slugValue === '') return;

    setAutosaveStatus(config.strings.autosaving);
    try {
      contentField.value = jar.toString();
      const formData = new FormData(editorForm);
      formData.set('editor_type', config.editorType || 'post');
      formData.set('action', 'save');
      const response = await fetch((config.basePath || '') + '/admin/autosave.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
      });
      const data = await response.json();
      if (data.success) {
        const now = new Date();
        setAutosaveStatus(config.strings.autosaved + ' ' + now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));
      } else {
        setAutosaveStatus(config.strings.autosave_failed);
      }
    } catch (e) {
      setAutosaveStatus(config.strings.autosave_failed);
    }
  }

  function scheduleAutosave() {
    if ((slugField?.value ?? '').trim() === '') return;
    clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(doAutosave, 10000);
  }

  [titleField, slugField, descriptionField, dateField, tagsField].forEach((field) => {
    field?.addEventListener('input', scheduleAutosave);
  });

  // Discard autosave on successful manual save (form submit).
  editorForm?.addEventListener('submit', () => { discardAutosave(); });

  // Show restore banner if an autosave exists.
  if (config.autosave) {
    const saved = config.autosave;
    const time  = new Date(saved.timestamp * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

    const preview = document.createElement('div');
    preview.className = 'autosave-preview';
    preview.hidden = true;
    preview.innerHTML =
      (saved.title   ? '<p><strong>' + config.strings.title_label + '</strong> ' + saved.title.replace(/</g, '&lt;') + '</p>' : '') +
      (saved.content ? '<pre class="autosave-preview-content">' + saved.content.replace(/</g, '&lt;') + '</pre>' : '');

    const banner = document.createElement('div');
    banner.className = 'notice autosave-restore-notice';
    banner.innerHTML =
      config.strings.autosave_banner.replace('{time}', time) + ' ' +
      '<button type="button" class="autosave-btn" id="autosave-view-btn">' + config.strings.view + '</button> ' +
      '<button type="button" class="autosave-btn" id="autosave-restore-btn">' + config.strings.restore + '</button> ' +
      '<button type="button" class="autosave-btn delete" id="autosave-discard-btn">' + config.strings.discard + '</button>';

    const main = document.querySelector('main');
    if (main) {
      main.insertBefore(preview, main.firstChild);
      main.insertBefore(banner, preview);
    }

    document.getElementById('autosave-view-btn')?.addEventListener('click', () => {
      const isHidden = preview.hidden;
      preview.hidden = !isHidden;
      document.getElementById('autosave-view-btn').textContent = isHidden ? config.strings.hide : config.strings.view;
    });

    document.getElementById('autosave-restore-btn')?.addEventListener('click', () => {
      const titleField2 = editorForm?.querySelector('[name="title"]');
      const descField2  = editorForm?.querySelector('[name="description"]');
      const tagsField2  = editorForm?.querySelector('[name="tags"]');
      const dateField2  = editorForm?.querySelector('[name="date"]');
      const navField2   = editorForm?.querySelector('[name="include_in_nav"]');
      const statusField = editorForm?.querySelector('[name="status"]');

      if (titleField2  && saved.title       != null) titleField2.value  = saved.title;
      if (descField2   && saved.description != null) descField2.value   = saved.description;
      if (tagsField2   && saved.tags        != null) {
        tagsField2.value = saved.tags;
        tagsField2.dispatchEvent(new Event('change'));
      }
      if (dateField2   && saved.date        != null) dateField2.value   = saved.date;
      if (navField2    && saved.include_in_nav != null) navField2.value = saved.include_in_nav;
      if (statusField  && saved.status      != null) statusField.value  = saved.status;
      if (saved.content != null) {
        jar.updateCode(saved.content);
        contentField.value = saved.content;
      }

      banner.remove();
    });

    document.getElementById('autosave-discard-btn')?.addEventListener('click', async () => {
      await discardAutosave();
      banner.remove();
    });
  }

  // Handle editor scroll and cursor position restoration on form submit / reload
  const scrollContainer = document.querySelector('.admin-main');
  const pendingRestoreStr = sessionStorage.getItem('editor_pending_restore');
  if (pendingRestoreStr) {
    try {
      const restoreData = JSON.parse(pendingRestoreStr);
      const urlParams = new URLSearchParams(window.location.search);
      const urlSlug = (urlParams.get('slug') || '').trim();
      const currentSlug = (slugField?.value ?? urlSlug ?? '').trim();

      const savedSlug = (restoreData.slug || '').trim();
      const isSlugMatch = savedSlug === currentSlug;
      const isNewPostMatch = savedSlug === '' && currentSlug !== '';
      const isUrlSlugMatch = savedSlug === urlSlug;

      console.log('[Pureblog Editor] Found pending restore data:', restoreData);
      console.log('[Pureblog Editor] Slugs comparison:', { savedSlug, currentSlug, urlSlug, isSlugMatch, isNewPostMatch, isUrlSlugMatch });

      if (restoreData &&
          restoreData.editorType === config.editorType &&
          (isSlugMatch || isNewPostMatch || isUrlSlugMatch) &&
          Date.now() - restoreData.timestamp < 10000) {

        console.log('[Pureblog Editor] Restoring state...');

        // Tell browser not to automatically restore scroll, we will handle it
        if ('scrollRestoration' in history) {
          history.scrollRestoration = 'manual';
        }

        // Restore cursor/focus
        const restoreCursor = () => {
          if (restoreData.cursor) {
            try {
              editorContainer.focus({ preventScroll: true });
              jar.restore(restoreData.cursor);
              console.log('[Pureblog Editor] Cursor restored.');
            } catch (e) {
              console.warn('[Pureblog Editor] Failed to restore cursor:', e);
            }
          }
        };

        // Restore scroll position
        const restoreScroll = () => {
          const scrollValue = parseInt(restoreData.scroll, 10);
          if (!Number.isNaN(scrollValue)) {
            if (scrollContainer) {
              scrollContainer.scrollTop = scrollValue;
            } else {
              window.scrollTo(0, scrollValue);
            }
            console.log('[Pureblog Editor] Scroll restored to:', scrollValue, 'Current scrollTop:', scrollContainer ? scrollContainer.scrollTop : window.scrollY);
          }
        };

        // Run cursor restoration immediately and with a small delay
        restoreCursor();
        setTimeout(restoreCursor, 50);

        // Run scroll restoration at progressive intervals to defeat layout shift cap
        restoreScroll();
        setTimeout(restoreScroll, 50);
        setTimeout(restoreScroll, 150);
        setTimeout(restoreScroll, 300);
        setTimeout(restoreScroll, 600);
      } else {
        console.log('[Pureblog Editor] Restore conditions not met.');
      }
    } catch (e) {
      console.error('[Pureblog Editor] Error during restore parsing:', e);
    }
    sessionStorage.removeItem('editor_pending_restore');
  }

  editorForm?.addEventListener('submit', () => {
    try {
      const pos = jar.save();
      const state = {
        scroll: scrollContainer ? scrollContainer.scrollTop : window.scrollY,
        cursor: pos,
        editorType: config.editorType,
        slug: (slugField?.value ?? '').trim(),
        timestamp: Date.now()
      };
      sessionStorage.setItem('editor_pending_restore', JSON.stringify(state));
      console.log('[Pureblog Editor] Saved state:', state);
    } catch (e) {
      console.error('[Pureblog Editor] Error saving state:', e);
    }
  });

  if (uploadInput && uploadButton) {
    const toggleUpload = () => {
      uploadButton.disabled = !uploadInput.files || uploadInput.files.length === 0;
    };
    uploadInput.addEventListener('change', toggleUpload);
    toggleUpload();
  }

  if (uploadForm && editorForm) {
    uploadForm.addEventListener('submit', async (event) => {
      if (allowUploadSubmit) {
        return;
      }
      event.preventDefault();
      try {
        contentField.value = jar.toString();
        const formData = new FormData(editorForm);
        const response = await fetch(editorForm.action || window.location.href, {
          method: 'POST',
          body: formData,
          credentials: 'same-origin',
        });
        if (!response.ok) {
          throw new Error(config.strings.save_failed);
        }
        const responseUrl = new URL(response.url, window.location.origin);
        const savedSlug = responseUrl.searchParams.get('slug') || (slugField?.value ?? '').trim();
        const uploadSlug = uploadForm.querySelector('input[name="slug"]');
        if (savedSlug && uploadSlug) {
          uploadSlug.value = savedSlug;
        }

        if (config.editorType === 'post') {
          const savedDate = (dateField?.value ?? '').trim();
          const uploadDate = uploadForm.querySelector('input[name="date"]');
          if (savedDate && uploadDate) {
            uploadDate.value = savedDate;
          }
        }

        allowUploadSubmit = true;
        uploadForm.submit();
      } catch (error) {
        alert(config.strings.save_before_upload);
      }
    });
  }

  previewButton?.addEventListener('click', () => {
    const statusValue = statusField?.value ?? '';
    const slugValue = (slugField?.value ?? '').trim();
    if (statusValue === 'published' && slugValue !== '') {
      window.open(`${config.basePath || ''}/${encodeURIComponent(slugValue)}`, '_blank');
      return;
    }

    const form = document.createElement('form');
    form.method = 'post';
    form.action = (config.basePath || '') + '/admin/preview.php';
    form.target = '_blank';

    const fields = [
      { name: 'editor_type', value: config.editorType || 'post' },
      { name: 'slug', value: slugField?.value ?? '' },
      { name: 'markdown', value: jar.toString() },
      { name: 'title', value: titleField?.value ?? '' },
      { name: 'description', value: descriptionField?.value ?? '' },
      { name: 'csrf_token', value: config.csrfToken || '' },
    ];

    if (config.editorType === 'post') {
      fields.splice(3, 0, { name: 'date', value: dateField?.value ?? '' });
      fields.splice(4, 0, { name: 'tags', value: tagsField?.value ?? '' });
    }

    const layoutInput = editorForm?.querySelector('input[name="post_layout"]');
    if (layoutInput) {
      fields.push({ name: 'layout', value: layoutInput.value });
    }

    editorForm?.querySelectorAll('[name^="layout_field__"]').forEach((el) => {
      if (el.type === 'checkbox') {
        fields.push({ name: el.name, value: el.checked ? '1' : '' });
      } else if (el.type !== 'hidden') {
        fields.push({ name: el.name, value: el.value });
      }
    });

    fields.forEach(({ name, value }) => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = value;
      form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
    form.remove();
  });

  document.addEventListener('keydown', (event) => {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') {
      event.preventDefault();
      editorForm?.requestSubmit();
    }
  });

  const notices = document.querySelectorAll('[data-auto-dismiss]');
  if (notices.length) {
    setTimeout(() => {
      notices.forEach((notice) => {
        notice.style.opacity = '0';
        notice.style.transform = 'translateX(30px)';
        setTimeout(() => notice.remove(), 250);
      });
      const url = new URL(window.location.href);
      ['saved', 'uploaded', 'upload_error'].forEach((param) => url.searchParams.delete(param));
      window.history.replaceState({}, document.title, url.toString());
    }, 2500);
  }

  document.querySelectorAll('.copy-markdown').forEach((button) => {
    button.addEventListener('click', async () => {
      const markdown = button.getAttribute('data-markdown') || '';
      if (markdown === '') {
        return;
      }
      try {
        await navigator.clipboard.writeText(markdown);
        button.innerHTML = '<svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-circle-check"></use></svg> ' + config.strings.copied;
        setTimeout(() => {
          button.innerHTML = '<svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-copy"></use></svg> ' + config.strings.copy;
        }, 1500);
      } catch (error) {
        alert(config.strings.copy_failed);
      }
    });
  });

  // Gallery Picker Dialogue & Cursor Tracking
  let lastSavedCursor = null;

  function recordEditorCursor() {
    try {
      const pos = jar.save();
      if (pos && typeof pos.start === 'number') {
        lastSavedCursor = pos;
      }
    } catch (e) {}
  }

  ['keyup', 'mouseup', 'touchend', 'input'].forEach((evt) => {
    editorContainer.addEventListener(evt, recordEditorCursor);
  });
  document.addEventListener('selectionchange', () => {
    const sel = window.getSelection();
    if (sel && sel.rangeCount > 0 && editorContainer.contains(sel.anchorNode)) {
      recordEditorCursor();
    }
  });

  function createGalleryPickerDialog() {
    let dialog = document.getElementById('gallery-picker-dialog');
    if (!dialog) {
      dialog = document.createElement('dialog');
      dialog.id = 'gallery-picker-dialog';
      dialog.className = 'gallery-picker-dialog';
      document.body.appendChild(dialog);

      dialog.addEventListener('click', (e) => {
        if (e.target === dialog) {
          dialog.close();
        }
      });
    }
    return dialog;
  }

  function openGalleryPicker(triggerButton) {
    const listItems = document.querySelectorAll('.image-list li');
    if (!listItems.length) {
      return;
    }

    const dialog = createGalleryPickerDialog();
    const strings = config.strings || {};
    const titleText = strings.gallery_picker_title || 'Select images for gallery';
    const selectAllText = strings.gallery_picker_select_all || 'Select all';
    const deselectAllText = strings.gallery_picker_deselect_all || 'Deselect all';
    const cancelText = strings.gallery_picker_cancel || 'Cancel';
    const noSelectedText = strings.gallery_picker_no_selected || 'Please select at least one image.';

    const images = [];
    listItems.forEach((li, idx) => {
      const copyBtn = li.querySelector('.copy-markdown');
      const imgEl = li.querySelector('.image-list-preview');
      const codeEl = li.querySelector('code');
      const markdown = copyBtn?.getAttribute('data-markdown') || '';
      const src = imgEl?.getAttribute('src') || '';
      const filename = codeEl?.textContent || `image-${idx + 1}`;
      if (markdown) {
        images.push({ id: `gp-img-${idx}`, markdown, src, filename });
      }
    });

    if (!images.length) {
      return;
    }

    dialog.innerHTML = `
      <h3>${titleText}</h3>
      <div class="gallery-picker-toolbar">
        <span class="gallery-picker-count" id="gallery-picker-count"></span>
        <div class="gallery-picker-toolbar-actions">
          <button type="button" class="link-button" id="gallery-picker-select-all">${selectAllText}</button>
          <button type="button" class="link-button" id="gallery-picker-deselect-all">${deselectAllText}</button>
        </div>
      </div>
      <ul class="gallery-picker-list">
        ${images.map(img => `
          <li class="gallery-picker-item" data-id="${img.id}">
            <input type="checkbox" id="${img.id}" value="${img.markdown.replace(/"/g, '&quot;')}">
            <img src="${img.src.replace(/"/g, '&quot;')}" class="gallery-picker-thumb" alt="${img.filename.replace(/"/g, '&quot;')}" loading="lazy">
            <code class="gallery-picker-filename">${img.filename.replace(/</g, '&lt;')}</code>
          </li>
        `).join('')}
      </ul>
      <div class="gallery-picker-footer">
        <button type="button" class="link-button" id="gallery-picker-cancel">${cancelText}</button>
        <button type="button" class="accent-button" id="gallery-picker-submit"></button>
      </div>
    `;

    const countEl = dialog.querySelector('#gallery-picker-count');
    const submitBtn = dialog.querySelector('#gallery-picker-submit');
    const cancelBtn = dialog.querySelector('#gallery-picker-cancel');
    const selectAllBtn = dialog.querySelector('#gallery-picker-select-all');
    const deselectAllBtn = dialog.querySelector('#gallery-picker-deselect-all');
    const checkboxes = dialog.querySelectorAll('.gallery-picker-item input[type="checkbox"]');

    function updateCount() {
      const selected = Array.from(checkboxes).filter(cb => cb.checked).length;
      const countTemplate = strings.gallery_picker_count || '{selected} of {total} selected';
      countEl.textContent = countTemplate.replace('{selected}', selected).replace('{total}', images.length);

      const insertTemplate = strings.gallery_picker_insert || 'Insert gallery ({count})';
      submitBtn.textContent = insertTemplate.replace('{count}', selected);
      submitBtn.disabled = selected === 0;
    }

    dialog.querySelectorAll('.gallery-picker-item').forEach(item => {
      item.addEventListener('click', (e) => {
        if (e.target.tagName.toLowerCase() !== 'input') {
          const cb = item.querySelector('input[type="checkbox"]');
          if (cb) {
            cb.checked = !cb.checked;
            updateCount();
          }
        }
      });
    });

    checkboxes.forEach(cb => {
      cb.addEventListener('change', updateCount);
    });

    selectAllBtn.addEventListener('click', () => {
      checkboxes.forEach(cb => { cb.checked = true; });
      updateCount();
    });

    deselectAllBtn.addEventListener('click', () => {
      checkboxes.forEach(cb => { cb.checked = false; });
      updateCount();
    });

    cancelBtn.addEventListener('click', () => {
      dialog.close();
    });

    submitBtn.addEventListener('click', async () => {
      const selectedMarkdown = Array.from(checkboxes)
        .filter(cb => cb.checked)
        .map(cb => cb.value);

      if (!selectedMarkdown.length) {
        alert(noSelectedText);
        return;
      }

      const currentCode = jar.toString();
      let start = currentCode.length;
      let end = currentCode.length;

      if (lastSavedCursor && typeof lastSavedCursor.start === 'number') {
        start = Math.max(0, Math.min(lastSavedCursor.start, currentCode.length));
        end = Math.max(0, Math.min(lastSavedCursor.end, currentCode.length));
      }

      let prefix = '\n\n';
      let suffix = '\n\n';
      if (start === 0 || currentCode.slice(0, start).endsWith('\n\n')) {
        prefix = '';
      } else if (currentCode.slice(0, start).endsWith('\n')) {
        prefix = '\n';
      }

      if (currentCode.slice(end).startsWith('\n\n') || currentCode.slice(end) === '') {
        suffix = '\n';
      } else if (currentCode.slice(end).startsWith('\n')) {
        suffix = '\n';
      }

      const gallerySnippet = `${prefix}::: gallery\n${selectedMarkdown.join('\n')}\n:::${suffix}`;
      const newCode = currentCode.slice(0, start) + gallerySnippet + currentCode.slice(end);

      jar.updateCode(newCode);
      contentField.value = newCode;
      scheduleAutosave();

      const newPos = {
        start: start + gallerySnippet.length,
        end: start + gallerySnippet.length,
        dir: '<-',
      };
      try {
        jar.restore(newPos);
        lastSavedCursor = newPos;
      } catch (e) {}
      editorContainer.focus();

      try {
        await navigator.clipboard.writeText(gallerySnippet.trim());
      } catch (e) {}

      dialog.close();

      if (triggerButton) {
        const originalHtml = triggerButton.innerHTML;
        const insertedText = strings.gallery_inserted || 'Gallery inserted';
        triggerButton.innerHTML = '<svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-circle-check"></use></svg> ' + insertedText;
        setTimeout(() => {
          triggerButton.innerHTML = originalHtml;
        }, 1500);
      }
    });

    updateCount();
    dialog.showModal();
  }

  document.querySelectorAll('.insert-gallery-btn').forEach((button) => {
    button.addEventListener('mousedown', () => {
      recordEditorCursor();
    });
    button.addEventListener('click', () => {
      recordEditorCursor();
      openGalleryPicker(button);
    });
  });

  const featureImageInput = document.getElementById('feature-image-value');

  document.querySelectorAll('.feature-image-check').forEach((checkbox) => {
    checkbox.addEventListener('change', async () => {
      const url      = checkbox.getAttribute('data-url') || '';
      const filename = checkbox.getAttribute('data-filename') || '';
      const slugValue = (slugField?.value ?? '').trim();

      if (slugValue === '') {
        checkbox.checked = !checkbox.checked;
        alert(config.editorType === 'post'
          ? config.strings.save_post_first
          : config.strings.save_page_first);
        return;
      }

      if (checkbox.checked) {
        const currentUrl = featureImageInput?.value ?? '';
        if (currentUrl !== '' && currentUrl !== url) {
          const currentFilename = currentUrl.split('/').pop();
          const confirmed = confirm(
            (config.strings.feature_image_confirm || '')
              .replace('{filename}', filename)
              .replace('{current}', currentFilename)
          );
          if (!confirmed) {
            checkbox.checked = false;
            return;
          }
        }
      }

      const newFilename = checkbox.checked ? filename : '';

      try {
        const formData = new FormData();
        formData.append('slug', slugValue);
        formData.append('editor_type', config.editorType);
        formData.append('filename', newFilename);
        formData.append('csrf_token', config.csrfToken || '');

        const response = await fetch((config.basePath || '') + '/admin/set-feature-image.php', {
          method: 'POST',
          body: formData,
        });
        const data = await response.json();

        if (!data.success) {
          checkbox.checked = !checkbox.checked;
          alert(config.strings.feature_image_failed || 'Failed to update feature image.');
          return;
        }

        if (checkbox.checked) {
          document.querySelectorAll('.feature-image-check').forEach((cb) => {
            if (cb !== checkbox) {
              cb.checked = false;
              cb.closest('li')?.classList.remove('is-feature');
            }
          });
          checkbox.closest('li')?.classList.add('is-feature');
          if (featureImageInput) featureImageInput.value = url;
        } else {
          checkbox.closest('li')?.classList.remove('is-feature');
          if (featureImageInput) featureImageInput.value = '';
        }
      } catch (e) {
        checkbox.checked = !checkbox.checked;
        alert(config.strings.feature_image_failed || 'Failed to update feature image.');
      }
    });
  });

  editorContainer.addEventListener('dragover', (event) => {
    event.preventDefault();
  });

  editorContainer.addEventListener('drop', async (event) => {
    event.preventDefault();
    if (!event.dataTransfer.files.length) {
      return;
    }

    const slugValue = (slugField?.value ?? '').trim();
    const dateValue = (dateField?.value ?? '').trim();
    if (slugValue === '' || (config.editorType === 'post' && dateValue === '')) {
      alert(config.editorType === 'post'
        ? config.strings.save_post_first
        : config.strings.save_page_first);
      return;
    }

    for (const file of event.dataTransfer.files) {
      if (!file.type.startsWith('image/')) {
        continue;
      }

      try {
        const formData = new FormData();
        formData.append('image', file);
        formData.append('slug', slugValue);
        if (config.editorType === 'post') {
          formData.append('date', dateValue);
        } else {
          formData.append('editor_type', 'page');
        }
        formData.append('csrf_token', config.csrfToken || '');

        const response = await fetch((config.basePath || '') + '/admin/upload-image.php', {
          method: 'POST',
          body: formData,
        });

        if (!response.ok) {
          throw new Error(config.strings.upload_failed || 'Upload failed');
        }

        const redirectUrl = new URL(response.url);
        const markdown = redirectUrl.searchParams.get('uploaded');
        if (!markdown) {
          throw new Error(redirectUrl.searchParams.get('upload_error') || config.strings.upload_failed || 'Upload failed');
        }
        insertTextAtCursor(jar, editorContainer, markdown);
      } catch (error) {
        alert(error?.message || config.strings.upload_failed);
      }
    }
  });

  // Sidebar Pop-out Toggle Logic
  const sidebarToggleTab = document.getElementById('sidebar-toggle-tab');
  const sidebarElement = document.querySelector('.editor-sidebar');

  if (sidebarToggleTab && sidebarElement) {
    // Dynamically inject the mobile backdrop overlay if not present
    let sidebarOverlay = document.getElementById('editor-sidebar-overlay');
    if (!sidebarOverlay) {
      sidebarOverlay = document.createElement('div');
      sidebarOverlay.id = 'editor-sidebar-overlay';
      sidebarOverlay.className = 'editor-sidebar-overlay';
      document.body.appendChild(sidebarOverlay);
    }

    const isSidebarOpenKey = `${config.editorType || 'editor'}:sidebar-open`;

    const setSidebarOpenState = (isOpen) => {
      document.body.classList.toggle('editor-sidebar-open', isOpen);
      localStorage.setItem(isSidebarOpenKey, isOpen ? 'true' : 'false');
    };

    // Initialize sidebar state from localStorage (defaulting to true/open on desktop, closed on mobile/tablet)
    const savedState = localStorage.getItem(isSidebarOpenKey);
    const isSmallScreen = window.innerWidth < 1025;

    if (isSmallScreen || savedState === 'false') {
      setSidebarOpenState(false);
    } else {
      setSidebarOpenState(true);
    }

    sidebarToggleTab.addEventListener('click', () => {
      document.body.classList.add('sidebar-ready');
      const isOpen = document.body.classList.contains('editor-sidebar-open');
      setSidebarOpenState(!isOpen);
    });

    sidebarOverlay.addEventListener('click', () => {
      document.body.classList.add('sidebar-ready');
      setSidebarOpenState(false);
    });
  }
})();
