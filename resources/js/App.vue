<template>
  <v-main>
    <v-app>
      <Menu v-if="is_login == true"></Menu>
      <LoginMain v-if="is_login == false"></LoginMain>
      <v-container>
        <router-view v-if="is_login == true"></router-view>
      </v-container>
    </v-app>
  </v-main>
</template>

<script>
import store from "./store/index";
import Menu from "./components/Menu.vue";
import Home from "./views/Property/Property.vue";
import { mapGetters } from "vuex";
import LoginMain from "./Main.vue";

export default {
  Home,
  store,
  name: "app",
  components: {
    Menu,
    LoginMain,
  },
  computed: {
    is_login() {
      return store.state.auth.status.loggedIn;
    },
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
