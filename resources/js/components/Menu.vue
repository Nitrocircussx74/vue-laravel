<template>
  <nav>
    <v-app-bar app>
      <v-app-bar-nav-icon @click="drawer = !drawer"> </v-app-bar-nav-icon>
      <v-toolbar-title>Vuetify </v-toolbar-title>
      <v-spacer></v-spacer>
      <v-col cols="auto">
        {{ user.user.name }}
      </v-col>

      <v-menu offset-y>
        <template v-slot:activator="{ on, attrs }">
          <v-btn small icon v-bind="attrs" v-on="on">
            <v-icon>keyboard_arrow_down</v-icon>
          </v-btn>
        </template>

        <v-list>
          <v-list-item
            v-for="(item, index) in toolbar_menu"
            :key="index"
            @click="menuActionClick(item.action)"
          >
            <v-list-item-title>{{ item.title }}</v-list-item-title>
          </v-list-item>
        </v-list>
      </v-menu>
    </v-app-bar>
    <v-navigation-drawer app v-model="drawer">
      <v-list-item>
        <v-list-item-content>
          <v-list-item-title class="title"> New Nabour </v-list-item-title>
        </v-list-item-content>
      </v-list-item>

      <v-divider></v-divider>
      <v-list-item-group>
        <v-list-item
          v-for="item in items"
          :key="item.title"
          router
          :to="item.route"
        >
          <v-list-item-icon>
            <v-icon dark color="#7E6990" v-text="item.icon"></v-icon>
          </v-list-item-icon>
          <v-list-item-title>{{ item.title }}</v-list-item-title>
        </v-list-item>
      </v-list-item-group>
    </v-navigation-drawer>
  </nav>
</template>

<script>
import currentUser from "../store/modules/currentUser";

import { mapGetters, mapActions } from "vuex";
export default {
  mounted() {},
  computed: {
    ...mapGetters({
      user: "currentUser/user",
      tokens: "currentUser/token",
      is_login: "currentUser/is_login",
    }),
  },
  methods: {
    ...mapActions({
      singout: "currentUser/logoutUser",
    }),
    logout() {
      this.singout().then(() => {
        if (this.$route.path != "/") {
          this.$router.push({
            name: "app",
          });
        }
      });
    },
    menuActionClick(action) {
      if (action === "test") {
        console.log("TEST!!");
      } else if (action === "logout") {
        console.log("logout!!");
        this.logout();
      }
    },
  },

  data() {
    return {
      toolbar_menu: [
        { title: "TEST", icon: "mdi-view-dashboard", action: "test" },
        { title: "Logout", icon: "mdi-view-dashboard", action: "logout" },
      ],
      items: [
        { title: "Admin", icon: "lock", route: "/admin" },
        { title: "Property", icon: "mdi-view-dashboard", route: "/property" },
        { title: "About", icon: "mdi-help-box", route: "/about" },
        {
          title: "Property Unit",
          icon: "mdi-help-box",
          route: "/front/property-unit",
        },
        // { title: "Logout", icon: "lock", route: "/" },
      ],
      right: null,
      drawer: true,
    };
  },
};
</script>

<style>
</style>