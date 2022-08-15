|-- Console
|   |-- Commands
|   |   |-- AdTask.php			广告后台任务
|   |   |-- AdvanceNotice.php	月底续费前预通知客户及时充值
|   |   |-- AnalysisLabel.php	统计分析客户标签
|   |   |-- ArrearsNotice.php	月底续费未完成语音通知
|   |   |-- CertCostTask.php	统计认证成本
|   |   |-- FacTextAccount.php	初始化服务商表is_test_account字段
|   |   |-- FacilitatorBill.php	统计服务商账号当月消费详情
|   |   |-- IniAccount.php		修复公司表部分记录账号字段未加密的问题
|   |   |-- MealUsageTask.php	统计套餐使用量
|   |   |-- OpenAccountTime.php 开户时间同步到redis的com.info键
|   |   |-- Remit.php			泰隆银行线下汇款到账脚本
|   |   |-- SaveWorkerId.php	修补53cloud库work表id6d为空的问题
|   |   |-- StatsDayTrait.php	按月统计的通用方法
|   |   |-- StatsMonthTrait.php	按月统计的通用方法
|   |   |-- TaskEntry.php		接收rabbitMq消息的任务分发
|   |   |-- TestCli.php 		测试、生成节假日配置、初始化scrm
|   |   `-- WxInit.php
|   `-- Kernel.php 定义定时任务
|-- Facades
|   `-- kfRedisFacade.php		redis的门面
|-- Lib
|   |-- BankRemitService.php	在Remit.php中调用
|   |-- BasicApi.php 			基础请求类
|   |-- CallApi.php 			呼叫中心接口
|   |-- FacilitatorApi.php 		服务商接口
|   |-- RabbitMq.php 			自己封装的rabbitMQ类
|   |-- RedisOperation.php 		redis操作类
|   |-- RenewApi.php 			自动续费接口
|   |-- ScrmApi.php 			scrm接口
|   |-- Tag 						标签分析相关处理的类,在AnalysisLabel.php中调用
|   |   |-- ArrearsHandle.php 		欠费
|   |   |-- BalanceHandle.php 		余额
|   |   |-- Handle.php 				处理基类
|   |   |-- MemberHandle.php 		会员等级
|   |   |-- MonthConsumHandle.php 	月消费额度
|   |   |-- PaymentHandle.php 		充值类型
|   |   `-- RechargeHandle.php 		一年中的充值次数
|   |-- TalkApi.php 				客服系统接口
|   |-- Task 						TaskEntry.php中调用的任务类的目录
|   |-- appidApi.php 	saas接口获取令牌
|   |-- aqptApi.php 	安全平台接口
|   |-- eiisysApi.php 	海翕云接口
|   |-- helpers.php 	自定义函数
|   |-- mTalkApi.php 	客服系统主项目接口
|   |-- saasApi.php 	saas订单服务接口
|   `-- wx 对接微信相关的自定义类
|       |-- WxApi.php
|       |-- WxFacade.php
|       |-- WxServiceProvider.php
|       |-- config.php
|       `-- wx.php
