<template>
  <!-- <div>
     <h1>v-model</h1>


 </div> -->

  <v-main>
    <v-app>
      <Menu></Menu>
      <v-container>
        <router-view></router-view>
      </v-container>
    </v-app>
  </v-main>
</template>

<script>
import store from "./store/index";
import Menu from "./components/Menu.vue";

import ComA from "./components/ComA.vue";
import ComB from "./components/ComB.vue";
import Home from "./views/Home.vue";
import About from "./views/About.vue";
export default {
  store,
  name: "app",
  components: {
    Home,
    About,
    Menu,
    ComA,
    ComB,
  },
  data() {
    return {
      drawer: true,
      items: [
        { title: "Home", icon: "mdi-home-city" },
        { title: "My Account", icon: "mdi-account" },
        { title: "Users", icon: "mdi-account-group-outline" },
      ],
      mini: true,
    };
  },
};
</script>
