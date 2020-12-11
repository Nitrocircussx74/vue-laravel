<template>
  <!-- <div class="row justify-content-center">
    <div class="col-md-12"> -->
  <v-container class="my-12">
    <div class="card">
      <div class="card-header"><h3>รายชื่อนิติบุคคล</h3></div>
      <div class="card-body">
        <v-card-title>
          รายชื่อนิติบุคคล
          <v-spacer></v-spacer>
          <v-text-field
            v-model="search"
            append-icon="mdi-magnify"
            label="Search"
          ></v-text-field>
        </v-card-title>
        <v-data-table
          :headers="headers"
          :search="search"
          :items="property_list"
          item-key="property_list.name"
          class="elevation-1"
        >
          <!-- :page.sync="page"
          hide-default-footer
          @page-count="pageCount = $event" -->
          <template v-slot:item.smart_bill="{ item }">
            <v-chip :color="getColor(item.smart_bill)" dark>
              {{ item.smart_bill }}
            </v-chip>
          </template>
          <template v-slot:item.active_status="{ item }">
            <v-chip :color="getColor(item.active_status)" dark>
              {{ item.active_status }}
            </v-chip>
          </template>
          <template v-slot:item.actions="{ item }">
            <div class="text-center">
              <v-menu offset-y>
                <template v-slot:activator="{ on, attrs }">
                  <v-btn
                    color="light-blue darken-3"
                    dark
                    v-bind="attrs"
                    v-on="on"
                  >
                    เลือกการจัดการ
                  </v-btn>
                </template>
                <v-list>
                  <v-list-item
                    v-for="(row, index) in toolbar_menu"
                    :key="index"
                    link
                    @click="menuActionClick(item, row)"
                  >
                    <v-list-item-icon>
                      <v-icon dark color="#7E6990" v-text="row.icon"></v-icon>
                    </v-list-item-icon>
                    <v-list-item-title>{{ row.title }}</v-list-item-title>
                  </v-list-item>
                </v-list>
              </v-menu>
            </div>
          </template>
        </v-data-table>
        <!-- <div class="text-center pt-2">
          <v-pagination v-model="page" :length="pageCount"></v-pagination>
        </div> -->
      </div>
    </div>
    <!-- </div>
  </div> -->
  </v-container>
</template>
<script>
import { mapGetters, mapActions } from "vuex";
// import { required, minLength, between } from "vuelidate/lib/validators";
export default {
  data: () => ({
    search: "",
    toolbar_menu: [
      { title: "ดูข้อมูล", icon: "mdi-eye", action: "view" },
      { title: "แก้ไข", icon: "mdi-open-in-new", action: "edit" },
    ],
    property_list: [],
    pagination: {},

    headers: [
      {
        text: "ลำดับ",
        // align: "start",
        value: "key",
        width: "2%",
      },
      {
        text: "รายชื่อนิติบุคคล",
        // align: "start",
        value: "property_name",
        width: "10%",
      },
      { text: "Smart Bill Payment", value: "smart_bill", width: "5%" },
      { text: "ลูกบ้าน", value: "count_user", width: "5%" },
      { text: "จังหวัด	", value: "province", width: "5%" },
      { text: "สถานะ", value: "active_status", width: "5%" },
      { text: "กระบวนการ", value: "actions", sortable: false, width: "5%" },

      //   { text: "Actions", value: "actions", sortable: false },
    ],
  }),
  mounted() {
    this.getProperty();
  },
  computed: {},
  watch: {},
  methods: {
    ...mapActions({
      get_property: "PropertyList/getProperty",
    }),
    async getProperty() {
      this.get_property().then((res) => {
        this.loading = true;
        let list = res.data.property_list;

        list.forEach((item, i) => {
          item.key = i + 1;
        });
        // console.log(list);

        this.property_list = list;
        this.total = res.data.property_list.length;
      });
    },
    getColor(data) {
      if (data == "ไม่เปิดใช้งาน") return "red";
      else return "green";
    },
    menuActionClick(item, row) {
      console.log(item, row);
      if (row.action === "view") {
        console.log(item.id);
        console.log("view");
      } else if (row.action === "edit") {
        console.log("edit");
      }
    },
  },
};
</script>

