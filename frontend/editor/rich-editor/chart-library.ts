/** Chart.js только с нужными видами графиков: столбцы, линии, круговая диаграмма, легенда и подсказки. */
import {
  ArcElement,
  BarController,
  BarElement,
  CategoryScale,
  Chart,
  Legend,
  LinearScale,
  LineController,
  LineElement,
  PieController,
  PointElement,
  Tooltip,
} from 'chart.js'

Chart.register(
  ArcElement,
  BarController,
  BarElement,
  CategoryScale,
  Legend,
  LinearScale,
  LineController,
  LineElement,
  PieController,
  PointElement,
  Tooltip,
)

// Холст не наследует шрифт страницы: подписи графиков получают его при загрузке библиотеки.
Chart.defaults.font.family = getComputedStyle(document.body).fontFamily

export { Chart }
