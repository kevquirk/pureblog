(() => {
  const config = window.PureblogEditorConfig || {};
  const contentField = document.getElementById('content');
  if (!contentField || typeof CodeMirror === 'undefined') {
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

  const cm = CodeMirror.fromTextArea(contentField, {
    mode: { name: 'markdown', highlightFormatting: true, html: true },
    lineNumbers: false,
    lineWrapping: true,
    viewportMargin: Infinity,
    inputStyle: 'contenteditable',
    spellcheck: true,
  });

  cm.addKeyMap({
    'Ctrl-B': (editor) => wrapSelection(editor, '**'),
    'Cmd-B': (editor) => wrapSelection(editor, '**'),
    'Ctrl-I': (editor) => wrapSelection(editor, '*'),
    'Cmd-I': (editor) => wrapSelection(editor, '*'),
    'Ctrl-K': (editor) => insertLink(editor),
    'Cmd-K': (editor) => insertLink(editor),
  });

  function wrapSelection(editor, wrapper) {
    const doc = editor.getDoc();
    const selections = doc.listSelections();
    if (!selections.length) {
      const cursor = doc.getCursor();
      doc.replaceRange(wrapper + wrapper, cursor);
      doc.setCursor({ line: cursor.line, ch: cursor.ch + wrapper.length });
      editor.focus();
      return;
    }

    editor.operation(() => {
      selections.forEach((selection) => {
        const from = selection.from();
        const to = selection.to();
        const selectedText = doc.getRange(from, to);
        if (selectedText) {
          doc.replaceRange(wrapper + selectedText + wrapper, from, to);
          doc.setSelection(
            { line: from.line, ch: from.ch + wrapper.length },
            { line: to.line, ch: to.ch + wrapper.length }
          );
        } else {
          doc.replaceRange(wrapper + wrapper, from);
          doc.setCursor({ line: from.line, ch: from.ch + wrapper.length });
        }
      });
    });
    editor.focus();
  }

  function insertLink(editor) {
    const doc = editor.getDoc();
    const selection = doc.listSelections()[0];
    const from = selection ? selection.from() : doc.getCursor();
    const to = selection ? selection.to() : doc.getCursor();
    const selectedText = doc.getRange(from, to);
    if (selectedText) {
      const linkText = `[${selectedText}]()`;
      doc.replaceRange(linkText, from, to);
      doc.setCursor({ line: from.line, ch: from.ch + linkText.length - 1 });
    } else {
      const linkText = '[]()';
      doc.replaceRange(linkText, from);
      doc.setCursor({ line: from.line, ch: from.ch + 1 });
    }
    editor.focus();
  }

  function safeResize() {
    try { cm.setSize(null, 'auto'); } catch (e) {}
  }

  safeResize();
  cm.on('change', safeResize);
  cm.on('refresh', safeResize);

  const getScrollKey = () => {
    const slugValue = (slugField?.value ?? '').trim();
    return `${scrollKeyBase}:${slugValue || 'new'}`;
  };

  const storedScroll = sessionStorage.getItem(getScrollKey());
  if (storedScroll !== null) {
    const scrollValue = parseInt(storedScroll, 10);
    if (!Number.isNaN(scrollValue)) {
      window.scrollTo(0, scrollValue);
    }
    sessionStorage.removeItem(getScrollKey());
  }

  editorForm?.addEventListener('submit', () => {
    sessionStorage.setItem(getScrollKey(), String(window.scrollY));
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
        cm.save();
        const formData = new FormData(editorForm);
        const response = await fetch(editorForm.action || window.location.href, {
          method: 'POST',
          body: formData,
          credentials: 'same-origin',
        });
        if (!response.ok) {
          throw new Error('Save failed');
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
        alert('Unable to save before uploading image.');
      }
    });
  }

  previewButton?.addEventListener('click', () => {
    const statusValue = statusField?.value ?? '';
    const slugValue = (slugField?.value ?? '').trim();
    if (statusValue === 'published' && slugValue !== '') {
      window.open(`/${encodeURIComponent(slugValue)}`, '_blank');
      return;
    }

    const form = document.createElement('form');
    form.method = 'post';
    form.action = '/admin/preview.php';
    form.target = '_blank';

    const fields = [
      { name: 'editor_type', value: config.editorType || 'post' },
      { name: 'slug', value: slugField?.value ?? '' },
      { name: 'markdown', value: cm.getValue() },
      { name: 'title', value: titleField?.value ?? '' },
      { name: 'description', value: descriptionField?.value ?? '' },
      { name: 'csrf_token', value: config.csrfToken || '' },
    ];

    if (config.editorType === 'post') {
      fields.splice(3, 0, { name: 'date', value: dateField?.value ?? '' });
      fields.splice(4, 0, { name: 'tags', value: tagsField?.value ?? '' });
    }

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
      notices.forEach((notice) => notice.remove());
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
        button.innerHTML = '<svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-circle-check"></use></svg> Copied';
        setTimeout(() => {
          button.innerHTML = '<svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-copy"></use></svg> Copy';
        }, 1500);
      } catch (error) {
        alert('Unable to copy to clipboard. Please copy manually.');
      }
    });
  });

  cm.getWrapperElement().addEventListener('dragover', (event) => {
    event.preventDefault();
  });

  cm.getWrapperElement().addEventListener('drop', async (event) => {
    event.preventDefault();
    if (!event.dataTransfer.files.length) {
      return;
    }

    const slugValue = (slugField?.value ?? '').trim();
    const dateValue = (dateField?.value ?? '').trim();
    if (slugValue === '' || (config.editorType === 'post' && dateValue === '')) {
      alert(config.editorType === 'post'
        ? 'Save the post first so it has a slug and date.'
        : 'Save the page first so it has a slug.');
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

        const response = await fetch('/admin/upload-image.php', {
          method: 'POST',
          body: formData,
        });

        if (!response.ok) {
          throw new Error('Upload failed');
        }

        const alt = file.name.replace(/\.[^.]+$/, '').replace(/[-_]/g, ' ');
        const pathBase = `/content/images/${slugValue}`;
        const url = `${pathBase}/${file.name}`;
        const markdown = `![${alt}](${url})`;
        const doc = cm.getDoc();
        const cursor = doc.getCursor();
        doc.replaceRange(markdown, cursor);
      } catch (error) {
        alert('Image upload failed.');
      }
    }
  });
})();
