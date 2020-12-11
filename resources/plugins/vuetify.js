// src/plugins/vuetify.js

import Vue from 'vue'
import Vuetify from 'vuetify'
import 'vuetify/dist/vuetify.min.css'

Vue.use(Vuetify)

const opts = {}

// export default new Vuetify(opts)
export default new Vuetify({
    opts,
    breakpoint: {
      thresholds: {
        xs: 340,
        sm: 540,
        md: 800,
        lg: 1280,
      },
      scrollBarWidth: 24,
    },
  })