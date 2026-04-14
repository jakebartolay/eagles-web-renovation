import { useEffect } from 'react'

export default function useStylesheet(href) {
  useEffect(() => {
    if (!href) {
      return undefined
    }

    const link = document.createElement('link')
    link.rel = 'stylesheet'
    link.href = href
    link.dataset.codexManaged = 'true'

    document.head.appendChild(link)

    return () => {
      link.remove()
    }
  }, [href])
}
