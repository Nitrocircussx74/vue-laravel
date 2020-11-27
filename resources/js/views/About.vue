<template>
  <div class="row justify-content-center">
    <div class="col-md-12">
      <div class="card">
        <div class="card-header">About</div>
        <div class="card-body">
          <ComA></ComA>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import ComA from "../components/ComA.vue";
export default {
  name: "home",
  components: {
    ComA,
  },
};
</script>

<style>
</style>