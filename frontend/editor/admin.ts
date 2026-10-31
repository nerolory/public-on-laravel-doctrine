/**
 * Общие ассеты страниц админки MoonShine: стили списков, пользователей и статистики, модерация
 * и массовые действия в списках, автоотправка фильтров.
 */
import { mountAutoSubmit } from './admin/auto-submit'
import { mountBulkSelection } from './admin/bulk-selection'
import { mountModeration } from './admin/moderation'
import './admin/styles/admin.scss'

mountModeration()
mountBulkSelection()
mountAutoSubmit()
