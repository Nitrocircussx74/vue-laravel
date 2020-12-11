<template>
  <div class="content-area">
    <div class="page-title">
      <div class="title-env">
        <h1 class="title">Mails&Parcels</h1>
        <p class="description">จดหมายและพัสดุ</p>
      </div>
      <div class="breadcrumb-env">
        <ol class="breadcrumb bc-1">
          <li>
            <a href="#"><i class="fa-home"></i>หน้าหลัก</a>
          </li>
          <li class="active">
            <strong>ข้อมูลที่พักอาศัย</strong>
          </li>
        </ol>
      </div>
    </div>
    <div class="row"></div>
  </div>
</template>

<script>
import Axios from "axios";
export default {
  data() {
    return { property_units: [] };
  },
  mounted() {},
  methods: {
    menuActionClick(action) {
      if (action === "logout") {
        this.logout();
      }
    },
    add() {
      //this.$store.dispatch("addAction")
    },
    del() {
      //this.$store.dispatch("delAction")
    },
  },
};
</script>

<style>
</style>