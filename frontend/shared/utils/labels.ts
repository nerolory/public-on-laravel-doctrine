/** Подпись из переводов редактора; отсутствующий ключ значит, что в переводах editor не хватает строки. */
export function labelText(labels: Record<string, string>, key: string): string {
  const text = labels[key]

  if (text === undefined) {
    throw new Error(`В переводах редактора нет подписи «${key}»`)
  }

  return text
}
