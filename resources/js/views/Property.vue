<template>
  <div class="row justify-content-center">
    <div class="col-md-12">
      <div class="card">
        <div class="card-header">Property</div>
        <div class="card-body"></div>

        <!-- <About></About> -->
      </div>
    </div>
  </div>
</template>
<script>
export default {
  methods: {
  },
  computed:{


  },
   created() {
    //    console.log(localStorage.getItem("token"));
    if (localStorage.hasOwnProperty("token")) {
      axios.defaults.headers.common["Authorization"] =
     localStorage.getItem("token");
      this.$store.dispatch("PropertyList/getProperty");
    } else {
      window.location.replace("/login");
    }
  },
};
</script>

