/** Реакция на статью или комментарий. */
export type ReactionType = 'like' | 'love' | 'insightful' | 'dislike' | 'angry' | 'boring'

/** Счётчики реакций и реакции текущего посетителя. */
export type ReactionSummary = { counts: Record<ReactionType, number>, mine: ReactionType[] }

/** Комментарий — как его отдаёт сервер. Права определяют, какие кнопки показать; проверяет их сервер. */
export type CommentView = {
  id: number
  body: string | null
  state: 'visible' | 'deleted' | 'hidden'
  author: { id: number, name: string, avatar: string | null } | null
  reply_to: { id: number, name: string } | null
  created_at: string
  edited: boolean
  reply_count: number
  reactions: ReactionSummary
  permissions: { edit: boolean, delete: boolean, reply: boolean, react: boolean }
}

/** Страница веток комментариев статьи. */
export type CommentPage = { items: CommentView[], has_more: boolean, total: number }
