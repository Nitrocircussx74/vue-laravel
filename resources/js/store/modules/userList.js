const actions ={
        getUser() {
            let res = axios.get("api/admin/all")
            return res;
        },
        async saveAdmin({dispatch},data)
        {
            let res = await axios.post("api/admin/save",data)
            // return  dispatch('getUser');
        },
        async delAdmin({dispatch},data)
        {
            let res = await axios.post("api/admin/del",data)
            // return  dispatch('getUser');
        },
};

export default { 
    namespaced:true,
    actions,
}
