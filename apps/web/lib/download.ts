export async function downloadFromApi(
  url: string,
  options: RequestInit,
  fallbackFilename: string
): Promise<void> {
  const response = await fetch(url, options);

  if (!response.ok) {
    const contentType = response.headers.get('content-type') ?? '';
    if (contentType.includes('application/json')) {
      const payload = await response.json();
      const message =
        (payload as { message?: string; error?: string }).message ??
        (payload as { message?: string; error?: string }).error ??
        'Export failed';
      throw new Error(message);
    }

    const text = await response.text();
    throw new Error(text || 'Export failed');
  }

  const blob = await response.blob();
  const disposition = response.headers.get('content-disposition') ?? '';
  const filenameMatch = disposition.match(/filename\*?=(?:UTF-8'')?"?([^";]+)/i);
  const filename = filenameMatch ? decodeURIComponent(filenameMatch[1]) : fallbackFilename;

  const urlObject = window.URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = urlObject;
  anchor.download = filename;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  window.URL.revokeObjectURL(urlObject);
}
