<template>
  <div>
    <navbar :app="this"></navbar>
    <!-- <div v-if="" ></div> -->
    <spinner v-if="loading"> </spinner>
    <div v-else-if="initiated">
      <router-view :app="this"></router-view>
    </div>
  </div>
</template>

<script>
import Navbar from "./components/Navbar";
export default {
  name: "app",

  components: {
    Navbar,
  },
  mounted() {
    this.init();
  },
  data() {
    return {
      user: null,
      loading: false,
      initiated: false,
      req: axios.create({
        baseUrl: BASE_URL,
      }),
    };
  },
  methods: {
    init() {
      this.loading = true;
      this.req.get("auth/init").then((response) => {
        this.user = response.data;
        this.loading = false;
        this.initiated = true;
      });
    },
  },
};
</script>

<style>
</style>