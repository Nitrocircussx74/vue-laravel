<template>
  <v-main>
    <v-app>
      <Menu v-if="is_login == true"></Menu>
      <v-container>
        <!-- {{token}} -->
        <!-- <Home></Home> -->
        <LoginMain v-if="is_login == false"></LoginMain>

        <router-view v-if="is_login == true"></router-view>
      </v-container>
    </v-app>
  </v-main>
</template>

<script>
import store from "./store/index";
import Menu from "./components/Menu.vue";
import Home from "./views/Home.vue";
import ComA from "./components/ComA.vue";
import ComB from "./components/ComB.vue";
import { mapGetters } from "vuex";
import LoginMain from "./Main.vue";
// import Home from "./views/Home.vue";
// import About from "./views/About.vue";

export default {
  Home,
  store,
  name: "app",
  components: {
    // Home,
    // About,
    Menu,
    ComA,
    ComB,
    LoginMain,
  },
  computed: {
    ...mapGetters({
      authenticated: "currentUser/authenticated",
      token: "currentUser/token",
      user: "currentUser/user",
      is_login: "currentUser/is_login",
    }),
  },
  data() {
    return {
      // isLogin: false,
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
