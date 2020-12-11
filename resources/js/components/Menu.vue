<template>
  <nav>
    <v-app-bar absolute temporary>
      <!-- <v-app-bar app> -->
      <v-app-bar-nav-icon @click="drawer = !drawer"> </v-app-bar-nav-icon>
      <v-toolbar-title class="text-uppercase grey--text"
        >New Nabour
      </v-toolbar-title>
      <v-spacer></v-spacer>
      <v-col class="grey--text" cols="auto">
        {{ user.name }}
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
    <!-- <v-navigation-drawer absolute temporary v-model="drawer"> -->
    <v-navigation-drawer absolute temporary v-model="drawer">
      <!-- <v-list-item>
        <v-list-item-content>
          <v-list-item-title class="title"> New Nabour </v-list-item-title>
        </v-list-item-content>
      </v-list-item>

      <v-divider></v-divider> -->
      <v-list-item-group>
        <v-list-item
          v-for="item in items"
          :key="item.title"
          router
          :to="item.route"
          @click="menuActionClick(item.action)"
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

import { mapActions } from "vuex";
export default {
  mounted() {},
  computed: {
    is_login() {
      return this.$store.state.auth.status.loggedIn;
    },
    user() {
      return this.$store.state.auth.user;
    },
  },
  methods: {
    ...mapActions({
      singout: "currentUser/logoutUser",
    }),
    logout() {
      this.$store.dispatch("auth/logout");
      this.$router.push({ name: "app" });
    },
    menuActionClick(action) {
      if (action === "logout") {
        this.logout();
      }
    },
  },

  data() {
    return {
      toolbar_menu: [
        //{ title: "TEST", icon: "mdi-view-dashboard", action: "test" },
        { title: "Logout", icon: "mdi-view-dashboard", action: "logout" },
      ],
      items: [
        { title: "Admin", icon: "lock", route: "/admin" },
        { title: "Property", icon: "mdi-view-dashboard", route: "/property" },
        {
          title: "Property Unit",
          icon: "mdi-help-box",
          route: "/front/property-unit",
        },
        { title: "Mails & Parcels", icon: "mdi-email", route: "/post" },
        { title: "Logout", icon: "lock", action: "logout" }, //mdi:card-search
        // { title: "Logout", icon: "lock", route: "/" },
      ],
      right: null,
      drawer: false,
    };
  },
};
</script>

<style>
</style>