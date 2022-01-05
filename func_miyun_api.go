package main

import (
	"fmt"
	"io/ioutil"
	"log"
	"net/http"

	"github.com/bitly/go-simplejson"
)

//发送GET请求并返回内容
func sendGetData(durl string) []byte {
	client := &http.Client{}
	var data []byte
	reqest, _ := http.NewRequest("GET", durl, nil)
	reqest.Header.Add("User-Agent", "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:95.0) Gecko/20100101 Firefox/95.0")
	response, err := client.Do(reqest)
	if err != nil {
		log.Println(err)
	}
	defer response.Body.Close()
	if response.StatusCode != 200 {
		fmt.Println("失败,请检查参数及网络设置")
		return data
	}
	data, _ = ioutil.ReadAll(response.Body)
	return data
}

//米云取token接口
func miyunTakeToken(apiName string, password string) string {
	durl := "http://api.miyun.pro/api/login?apiName=" + apiName + "&password=" + password
	json := sendGetData(durl)
	reTokenMsg, err := simplejson.NewJson(json) //使用simplejson处理json
	if err != nil {
		panic(err.Error())
	}
	message, _ := reTokenMsg.Get("message").String()
	if message == "ok" {
		token, _ := reTokenMsg.Get("token").String()
		return token
	}
	return "API账号或密码错误(账号为: MY. 开头，请在客户端查阅)"
}

//米云取号接口
func miyunTakePhoneNum(nowToken string, id string, operator string) string {
	durl := "http://api.miyun.pro/api/get_mobile?token=" + nowToken + "&project_id=" + id + "&operator=" + operator
	for {
		json := sendGetData(durl)
		reTokenMsg, err := simplejson.NewJson(json) //使用simplejson处理json
		if err != nil {
			panic(err.Error())
		}
		message, _ := reTokenMsg.Get("message").String()
		if message == "ok" {
			mobile, _ := reTokenMsg.Get("mobile").String()
			return mobile
		} else {
			msg, _ := reTokenMsg.Get("message").String()
			fmt.Println(msg)
			return ""
		}
	}
}

//米云拉黑接口
func miyunAddBlackList(token string, mobile string, id string) {
	durl := `http://api.miyun.pro/api/add_blacklist?token=` + token + `&project_id=` + id + `&phone_num=` + mobile
	for i := 0; i < 3; i++ {
		json := sendGetData(durl)
		reMsg, err := simplejson.NewJson(json) //使用simplejson处理json
		if err != nil {
			panic(err.Error())
		}
		message, _ := reMsg.Get("message").String()
		if message == "ok" {
			break
		}
	}
}

//米云释放接口
func miyunFreeMobile(token string, mobile string, id string) {
	durl := `http://api.miyun.pro/api/free_mobile?token=` + token + `&project_id=` + id + `&phone_num=` + mobile
	for i := 0; i < 3; i++ {
		json := sendGetData(durl)
		reMsg, err := simplejson.NewJson(json) //使用simplejson处理json
		if err != nil {
			panic(err.Error())
		}
		message, _ := reMsg.Get("message").String()
		if message == "ok" {
			break
		}
	}
}
