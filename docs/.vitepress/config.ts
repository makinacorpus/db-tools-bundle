import { defineConfig } from 'vitepress'

// https://vitepress.dev/reference/site-config
export default defineConfig({
  lang: 'en',
  title: 'DbToolsBundle',
  description: 'A PHP library to backup, restore and anonymize databases',
  srcDir: "content",
  base: "/",
  metaChunk: false,
  head: [
    ['link', {
      rel: 'icon',
      href: "data:image/svg+xml,%3C%3Fxml version='1.0' encoding='UTF-8' standalone='no'%3F%3E%3Csvg version='1.1' viewBox='0 0 162.94769 160.29467' width='244.06895' height='240.08168' xmlns='http://www.w3.org/2000/svg' xmlns:svg='http://www.w3.org/2000/svg'%3E%3Cpath d='m 64.176019,1.2999481 c -35.25899,0 -62.8806104,16.1131569 -62.8806104,36.6803579 v 62.880614 c 0,20.5672 27.6216204,36.68036 62.8806104,36.68036 35.258991,0 62.880601,-16.11316 62.880601,-36.68036 V 37.980306 c 0,-20.567201 -27.62161,-36.6803579 -62.880601,-36.6803579 z' style='fill:%23ffffff;fill-opacity:1;stroke:%23ffffff;stroke-width:2.5999;stroke-miterlimit:4;stroke-dasharray:none;stroke-opacity:1' /%3E%3Cpath d='m 121.81657,37.980306 c 0,17.364219 -25.807243,31.440307 -57.640551,31.440307 -31.833311,0 -57.6405632,-14.076088 -57.6405632,-31.440307 0,-17.36422 25.8072522,-31.4403068 57.6405632,-31.4403068 31.833308,0 57.640551,14.0760868 57.640551,31.4403068 z' opacity='0.2' style='fill:%23561fa8;stroke-width:0.319145;stroke-miterlimit:4;stroke-dasharray:none' /%3E%3Cpath d='m 64.176019,1.2999481 c -35.258994,0 -62.8806142,16.1131569 -62.8806142,36.6803579 v 62.880624 c 0,20.56719 27.6216202,36.68035 62.8806142,36.68035 35.258991,0 62.880601,-16.11316 62.880601,-36.68035 V 37.980306 c 0,-20.567201 -27.62161,-36.6803579 -62.880601,-36.6803579 z M 116.57652,69.420613 c 0,6.301161 -5.16145,12.726774 -14.15469,17.632772 -10.12639,5.521704 -23.711222,8.567484 -38.245811,8.567484 -14.534592,0 -28.119425,-3.04578 -38.245824,-8.567484 C 16.936958,82.147387 11.775507,75.721774 11.775507,69.420613 V 58.521306 c 11.174409,9.825097 30.280946,16.139358 52.400512,16.139358 22.119563,0 41.226091,-6.340462 52.400501,-16.139358 z M 25.930195,20.347534 C 36.056594,14.82583 49.641427,11.78005 64.176019,11.78005 c 14.534589,0 28.119421,3.04578 38.245811,8.567484 8.99324,4.905997 14.15469,11.33161 14.15469,17.632772 0,6.301161 -5.16145,12.726774 -14.15469,17.632772 -10.12639,5.521704 -23.711222,8.567483 -38.245811,8.567483 -14.534592,0 -28.119425,-3.045779 -38.245824,-8.567483 C 16.936958,50.70708 11.775507,44.281467 11.775507,37.980306 c 0,-6.301162 5.161451,-12.726775 14.154688,-17.632772 z m 76.491635,98.146156 c -10.12639,5.52171 -23.711222,8.56749 -38.245811,8.56749 -14.534592,0 -28.119425,-3.04578 -38.245824,-8.56749 C 16.936958,113.5877 11.775507,107.16208 11.775507,100.86093 V 89.961613 c 11.174409,9.825092 30.280946,16.139357 52.400512,16.139357 22.119563,0 41.226091,-6.340465 52.400501,-16.139357 v 10.899317 c 0,6.30115 -5.16145,12.72677 -14.15469,17.63276 z' style='fill:%23561fa8;stroke-width:2.5999;stroke-miterlimit:4;stroke-dasharray:none' /%3E%3Cpath d='m 159.98111,107.01098 c -0.66886,-1.65316 -2.78409,-2.1276 -4.09496,-0.91849 l -12.85256,11.86072 -5.49503,-1.18002 -1.18001,-5.49503 11.86071,-12.852561 c 1.20911,-1.31087 0.73467,-3.42609 -0.91849,-4.09496 -15.09679,-6.10695 -31.57652,5.00604 -31.57331,21.291241 -0.004,3.17544 0.64731,6.31755 1.91354,9.22961 l -19.202305,16.60309 c -0.04784,0.0383 -0.09249,0.0829 -0.137137,0.12438 -3.985958,3.98596 -3.985952,10.44846 3e-6,14.43442 3.985949,3.98595 10.448449,3.98596 14.434409,0 0.0415,-0.0415 0.0861,-0.0893 0.12438,-0.13395 l 16.5999,-19.20868 c 15.18306,6.66484 32.20866,-4.46741 32.19202,-21.04887 0.005,-2.95136 -0.56256,-5.87566 -1.67116,-8.6109 z' style='fill:%23ffffff;fill-opacity:1;stroke:%23ffffff;stroke-width:2.6;stroke-linecap:round;stroke-linejoin:round;stroke-miterlimit:4;stroke-dasharray:none;stroke-opacity:1' /%3E%3Cpath d='m 159.10089,115.62188 a 20.411022,20.411022 0 0 1 -30.27848,17.85965 l -17.87878,20.72994 a 7.6674103,7.6674103 0 0 1 -10.84335,-10.84335 l 20.72994,-17.87878 A 20.411022,20.411022 0 0 1 146.344,96.693846 l -12.75689,13.825284 1.8051,8.40041 8.40041,1.8051 13.82528,-12.75689 a 20.347238,20.347238 0 0 1 1.48299,7.65413 z' opacity='0.2' style='fill:%23561fa8;stroke-width:0.318922' /%3E%3Cpath d='m 159.98111,107.01098 a 2.5513778,2.5513778 0 0 0 -4.09496,-0.91849 l -12.85256,11.86071 -5.49503,-1.18001 -1.18001,-5.49503 11.86071,-12.852566 a 2.5513778,2.5513778 0 0 0 -0.91849,-4.094961 22.9624,22.9624 0 0 0 -31.57331,21.291247 23.070833,23.070833 0 0 0 1.91354,9.22961 l -19.202305,16.60309 c -0.04784,0.0383 -0.09249,0.0829 -0.137137,0.12438 a 10.206676,10.206676 0 0 0 14.434412,14.43442 c 0.0415,-0.0415 0.0861,-0.0893 0.12438,-0.13395 l 16.5999,-19.20868 a 22.9624,22.9624 0 0 0 32.19202,-21.04887 22.822074,22.822074 0 0 0 -1.67116,-8.6109 z m -21.29124,26.47055 a 17.904293,17.904293 0 0 1 -8.63323,-2.23246 2.5513778,2.5513778 0 0 0 -3.16371,0.56449 l -17.82775,20.64703 a 5.1027555,5.1027555 0 0 1 -7.21402,-7.21402 L 122.48224,127.422 a 2.5513778,2.5513778 0 0 0 0.56449,-3.16689 17.859644,17.859644 0 0 1 18.6123,-26.250493 l -9.95037,10.782763 a 2.5513778,2.5513778 0 0 0 -0.61871,2.26435 l 1.8051,8.39722 a 2.5513778,2.5513778 0 0 0 1.95818,1.95818 l 8.4036,1.8051 a 2.5513778,2.5513778 0 0 0 2.26435,-0.61871 l 10.78276,-9.95037 a 17.87878,17.87878 0 0 1 -17.61407,20.83838 z' style='fill:%23561fa8;stroke-width:0.319145;stroke-miterlimit:4;stroke-dasharray:none' /%3E%3C/svg%3E"
    }],
    ['meta', {
      property: 'og:image',
      content: 'https://raw.githubusercontent.com/makinacorpus/DbToolsBundle/main/docs/content/public/meta.png'
    }]
  ],
  themeConfig: {
    // https://vitepress.dev/reference/default-theme-config
    logo: {
      light: '/logo.svg',
      dark: '/logo-d.svg'
    },
    nav: [
      { text: 'Home', link: '/' },
    ],
    editLink: {
      pattern: 'https://github.com/makinacorpus/DbToolsBundle/blob/main/docs/content/:path',
      text: 'Edit this page on Github'
    },
    docFooter: {
      prev: 'Previous page',
      next: 'Next page'
    },
    outlineTitle: 'On this page',
    lastUpdated: {
      text: 'Last updated',
      formatOptions: {
        dateStyle: 'short'
      }
    },
    search: {
      provider: 'local'
    },
    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © 2023-present <a href="https://makina-corpus.com">Makina Corpus</a>'
    },
    socialLinks: [
      { icon: 'github', link: 'https://github.com/makinacorpus/DbToolsBundle' }
    ],
    sidebar: [
      {
        text: 'Getting Started',
        collapsed: false,
        items: [
          { text: 'Introduction', link: '/getting-started/introduction' },
          { text: 'Installation', link: '/getting-started/installation' },
          { text: 'Basics', link: '/getting-started/basics' },
          { text: 'Flavors', link: '/getting-started/flavors' },
          { text: 'Supported databases', link: '/getting-started/database-vendors' },
        ]
      },
      {
        text: 'Anonymization',
        collapsed: false,
        items: [
          { text: 'Essentials', link: '/anonymization/essentials' },
          { text: 'Core Anonymizers', link: '/anonymization/core-anonymizers' },
          { text: 'Extra packs', link: '/anonymization/packs' },
          { text: 'Custom Anonymizers', link: '/anonymization/custom-anonymizers' },
          { text: 'Anonymization command', link: '/anonymization/command' },
          { text: 'GDPR-friendly workflow', link: '/anonymization/workflow' },
          { text: 'Doctrine and inheritance', link: '/anonymization/doctrine-inheritance' },
          { text: 'Performance', link: '/anonymization/performance' },
          { text: 'Internals', link: '/anonymization/internals' },
        ]
      },
      {
        text: 'Going further',
        collapsed: false,
        items: [
          { text: 'Backup & Restore', link: '/backup_restore' },
          { text: 'Statistics', link: '/stats' },
          { text: 'Configuration basics', link: '/configuration/basics' },
          { text: 'Configuration reference', link: '/configuration/reference' },
          { text: 'Changelog', link: '/changelog'},
          { text: 'Upgrade guide', link: '/upgrade'},
        ]
      },
      {
        text: 'Contribute',
        collapsed: false,
        items: [
          { text: 'How to help ?', link: '/contribute/contribute' },
          { text: 'Development guide', link: '/contribute/guide' },
          { text: 'Creating a pack of anonymizers', link: '/contribute/pack' },
          { text: 'Code of conduct', link: '/contribute/code-of-conduct' },
        ]
      },
    ]
  }
})
