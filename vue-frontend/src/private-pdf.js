import { api } from './api.js';

export async function fetchPrivatePdf(bookId, signal) {
  const { data } = await api.get(`/library/${encodeURIComponent(bookId)}/download`, {
    responseType: 'blob',
    signal,
  });
  if (!(data instanceof Blob) || !data.type.includes('application/pdf')) {
    throw new Error('Respons PDF tidak valid.');
  }
  return data;
}

export async function downloadBook(book) {
  const blob = await fetchPrivatePdf(book.id);
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `${String(book.title || 'buku').replace(/[<>:"/\\|?*]/g, '_')}.pdf`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}
