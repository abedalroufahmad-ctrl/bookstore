/** Resize/compress camera photos before upload so analyze-cover starts sooner. */
export async function compressImageForUpload(
  file: File,
  options: { maxEdge?: number; quality?: number; maxBytes?: number } = {}
): Promise<File> {
  const maxEdge = options.maxEdge ?? 1800
  const quality = options.quality ?? 0.86
  const maxBytes = options.maxBytes ?? 950_000

  if (!file.type.startsWith('image/') || file.type.includes('gif')) {
    return file
  }

  // Already small enough — skip canvas work.
  if (file.size <= Math.min(maxBytes, 700_000)) {
    return file
  }

  const bitmap = await createImageBitmap(file)
  try {
    const scale = Math.min(1, maxEdge / Math.max(bitmap.width, bitmap.height))
    const width = Math.max(1, Math.round(bitmap.width * scale))
    const height = Math.max(1, Math.round(bitmap.height * scale))
    const canvas = document.createElement('canvas')
    canvas.width = width
    canvas.height = height
    const ctx = canvas.getContext('2d')
    if (!ctx) return file
    ctx.drawImage(bitmap, 0, 0, width, height)

    let q = quality
    let blob: Blob | null = null
    for (let i = 0; i < 4; i++) {
      blob = await new Promise<Blob | null>((resolve) =>
        canvas.toBlob((b) => resolve(b), 'image/jpeg', q)
      )
      if (!blob) break
      if (blob.size <= maxBytes || q <= 0.5) break
      q -= 0.1
    }
    if (!blob || blob.size >= file.size) return file

    const name = file.name.replace(/\.\w+$/, '') || 'cover'
    return new File([blob], `${name}.jpg`, { type: 'image/jpeg', lastModified: Date.now() })
  } finally {
    bitmap.close()
  }
}
