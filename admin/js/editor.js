(() => {
  const config = window.PureblogEditorConfig || {};
  const contentField = document.getElementById('content');
  if (!contentField || typeof window.createSyntaxEditor !== 'function') {
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

  const uploadForm = document.querySelector('.upload-form');
  const uploadInput = uploadForm?.querySelector('input[type="file"]');
  const uploadButton = uploadForm?.querySelector('button[type="submit"]');
  let allowUploadSubmit = false;

  // Initialize syntax editor on the main content textarea
  const mainEditor = window.createSyntaxEditor(contentField, 'markdown');

  contentField.addEventListener('input', () => {
    scheduleAutosave();
  });

  // Handle Markdown shortcut keydown events for a textarea
  function attachMarkdownShortcuts(textarea) {
    textarea.addEventListener('keydown', (event) => {
      const isCmdOrCtrl = event.metaKey || event.ctrlKey;
      if (!isCmdOrCtrl || event.shiftKey || event.altKey) {
        return;
      }
      const key = event.key.toLowerCase();
      if (key === 'b') {
        event.preventDefault();
        wrapSelection(textarea, '**');
      } else if (key === 'i') {
        event.preventDefault();
        wrapSelection(textarea, '*');
      } else if (key === 'k') {
        event.preventDefault();
        insertLink(textarea);
      }
    });
  }

  attachMarkdownShortcuts(contentField);

  // Initialize syntax editor for layout markdown editors if any
  const layoutEditors = [];
  document.querySelectorAll('textarea[data-layout-markdown]').forEach((textarea) => {
    const layoutEditor = window.createSyntaxEditor(textarea, 'markdown');
    textarea.addEventListener('input', () => {
      scheduleAutosave();
    });
    attachMarkdownShortcuts(textarea);
    layoutEditors.push(layoutEditor);
  });

  function replaceTextareaRange(textarea, text, newSelectionStart, newSelectionEnd) {
    textarea.focus();
    if (!document.execCommand || !document.execCommand('insertText', false, text)) {
      textarea.setRangeText(text, textarea.selectionStart, textarea.selectionEnd, 'end');
      textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }
    textarea.setSelectionRange(newSelectionStart, newSelectionEnd);
  }

  function wrapSelection(textarea, wrapper) {
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.slice(start, end);
    const newText = wrapper + selectedText + wrapper;
    const wLen = wrapper.length;

    if (selectedText.length > 0) {
      replaceTextareaRange(textarea, newText, start + wLen, end + wLen);
    } else {
      replaceTextareaRange(textarea, newText, start + wLen, start + wLen);
    }
  }

  function insertLink(textarea) {
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.slice(start, end);
    const newText = selectedText ? `[${selectedText}]()` : '[]()';

    if (selectedText.length > 0) {
      const cursorPos = start + selectedText.length + 3; // inside ()
      replaceTextareaRange(textarea, newText, cursorPos, cursorPos);
    } else {
      const cursorPos = start + 1; // inside []
      replaceTextareaRange(textarea, newText, cursorPos, cursorPos);
    }
  }

  function insertTextAtCursor(textarea, text) {
    const start = textarea.selectionStart;
    const newCursor = start + text.length;
    replaceTextareaRange(textarea, text, newCursor, newCursor);
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
      const statusField2 = editorForm?.querySelector('[name="status"]');

      if (titleField2  && saved.title       != null) titleField2.value  = saved.title;
      if (descField2   && saved.description != null) descField2.value   = saved.description;
      if (tagsField2   && saved.tags        != null) {
        tagsField2.value = saved.tags;
        tagsField2.dispatchEvent(new Event('change'));
      }
      if (dateField2   && saved.date        != null) dateField2.value   = saved.date;
      if (navField2    && saved.include_in_nav != null) navField2.value = saved.include_in_nav;
      if (statusField2 && saved.status      != null) statusField2.value = saved.status;
      if (saved.content != null) {
        mainEditor.updateCode(saved.content);
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

      if (restoreData &&
          restoreData.editorType === config.editorType &&
          (isSlugMatch || isNewPostMatch || isUrlSlugMatch) &&
          Date.now() - restoreData.timestamp < 10000) {

        if ('scrollRestoration' in history) {
          history.scrollRestoration = 'manual';
        }

        const restoreCursor = () => {
          if (restoreData.cursor && typeof restoreData.cursor.start === 'number') {
            try {
              contentField.focus({ preventScroll: true });
              contentField.setSelectionRange(restoreData.cursor.start, restoreData.cursor.end ?? restoreData.cursor.start);
            } catch (e) {}
          }
        };

        const restoreScroll = () => {
          const scrollValue = parseInt(restoreData.scroll, 10);
          if (!Number.isNaN(scrollValue)) {
            if (scrollContainer) {
              scrollContainer.scrollTop = scrollValue;
            } else {
              window.scrollTo(0, scrollValue);
            }
          }
        };

        restoreCursor();
        setTimeout(restoreCursor, 50);

        restoreScroll();
        setTimeout(restoreScroll, 50);
        setTimeout(restoreScroll, 150);
        setTimeout(restoreScroll, 300);
        setTimeout(restoreScroll, 600);
      }
    } catch (e) {}
    sessionStorage.removeItem('editor_pending_restore');
  }

  function saveEditorStateToSession() {
    try {
      const state = {
        scroll: scrollContainer ? scrollContainer.scrollTop : window.scrollY,
        cursor: {
          start: contentField.selectionStart,
          end: contentField.selectionEnd,
        },
        editorType: config.editorType,
        slug: (slugField?.value ?? '').trim(),
        timestamp: Date.now(),
      };
      sessionStorage.setItem('editor_pending_restore', JSON.stringify(state));
    } catch (e) {}
  }

  editorForm?.addEventListener('submit', () => {
    saveEditorStateToSession();
  });

  if (uploadInput && uploadButton) {
    const toggleUpload = () => {
      uploadButton.disabled = !uploadInput.files || uploadInput.files.length === 0;
    };
    uploadInput.addEventListener('change', toggleUpload);
    toggleUpload();
  }

  async function saveEditorBeforeSidebarAction() {
    clearTimeout(autosaveTimer);
    await doAutosave();

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
    if (!responseUrl.searchParams.get('saved')) {
      throw new Error(config.strings.save_failed);
    }
    await discardAutosave();
    return responseUrl.searchParams.get('slug') || (slugField?.value ?? '').trim();
  }

  if (uploadForm && editorForm) {
    uploadForm.addEventListener('submit', async (event) => {
      if (allowUploadSubmit) {
        return;
      }
      event.preventDefault();
      try {
        const savedSlug = await saveEditorBeforeSidebarAction();
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

        saveEditorStateToSession();
        allowUploadSubmit = true;
        uploadForm.submit();
      } catch (error) {
        alert(config.strings.save_before_upload);
      }
    });
  }

  // Save editor content before deleting an attached image in the sidebar
  document.querySelectorAll('.image-list form.inline-form').forEach((deleteForm) => {
    let allowDeleteSubmit = false;
    deleteForm.addEventListener('submit', async (event) => {
      if (allowDeleteSubmit || !editorForm) {
        return;
      }
      event.preventDefault();
      try {
        await saveEditorBeforeSidebarAction();
        saveEditorStateToSession();
        allowDeleteSubmit = true;
        deleteForm.submit();
      } catch (error) {
        alert(config.strings.save_failed);
      }
    });
  });

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
      { name: 'markdown', value: contentField.value },
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
    lastSavedCursor = {
      start: contentField.selectionStart,
      end: contentField.selectionEnd,
    };
  }

  ['keyup', 'mouseup', 'touchend', 'input', 'focus', 'select'].forEach((evt) => {
    contentField.addEventListener(evt, recordEditorCursor);
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

      const currentCode = contentField.value;
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
      const newCursorPos = start + gallerySnippet.length;

      contentField.setSelectionRange(start, end);
      replaceTextareaRange(contentField, gallerySnippet, newCursorPos, newCursorPos);
      lastSavedCursor = { start: newCursorPos, end: newCursorPos };

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

  contentField.addEventListener('dragover', (event) => {
    event.preventDefault();
  });

  contentField.addEventListener('drop', async (event) => {
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
        insertTextAtCursor(contentField, markdown);
      } catch (error) {
        alert(error?.message || config.strings.upload_failed);
      }
    }
  });

  // Sidebar Pop-out Toggle Logic
  const sidebarToggleTab = document.getElementById('sidebar-toggle-tab');
  const sidebarElement = document.querySelector('.editor-sidebar');

  if (sidebarToggleTab && sidebarElement) {
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
