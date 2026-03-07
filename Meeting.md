Ahmad Sheikh: This game. So, let me know if you can see my screen. Right. So, what we did is. So in this product grid, you know, I have given this options, you know, enable all, you know, if you want to enable all for wholesale. So these are the two columns: wholesale and track inventory. And if you, you know, I have let me just disable all.  
George Varkey: Okay.  
Ahmad Sheikh: Right. So when you will see in the front end, it will now nothing to display. Yes.  
George Varkey: Okay.  
Ahmad Sheikh: Right. So when we will enable this, it's local. So suppose you want to enable just, you know, few brands. So what you can do is it's nothing here as well.  
George Varkey: Yeah.  
Ahmad Sheikh: Inventory. So there, um, we can see. So we can see, uh, like this, I have filtered this, right? So when I filter this, so these are, I guess, um, 266\. So we can see 500\. So these are all the, you know, filtered products for this brand.  
George Varkey: Okay.  
Ahmad Sheikh: So on that, we can enable this. So it will only enable this particular filter results, right? And still, we have only enabled these products but not, you know, marked these as a track inventory. So in this inventory grid, this will not display. So once you mark the as trackable inventory. So I have a brand. And now we can see here, these will start. Yes. So now these are appearing.  
George Varkey: Perfect. Perfect. This is exactly what we need. But here it's saying, just a second. It's saying 5,000.  
Ahmad Sheikh: Right.  
George Varkey: 51,000 items. It's showing you everything. As you can see, you only enabled Riviera, but everything is showing.  
Ahmad Sheikh: Everything. Okay. Yeah, let me maybe.  
George Varkey: Now go back to the product.  
Ahmad Sheikh: There's some bug in.  
George Varkey: grid. You enabled.  
Ahmad Sheikh: that. Relax. Okay. Yeah, maybe this has some bug that after enabling, enabling this in inventory, it enable everything. So just refresh. We will enable this.  
George Varkey: I think it's because you selected that it did it for all.  
Ahmad Sheikh: one. Yeah, but I tested and.  
George Varkey: products. Remove the filter now and see if everything.  
Ahmad Sheikh: shouldn't be. Let me just now remove.  
George Varkey: got. Ah, no. Okay.  
Ahmad Sheikh: No, it's not. So.  
George Varkey: Now go to.  
Ahmad Sheikh: but. There's a problem here. Yeah, it's.  
George Varkey: Yeah. And also see why it's saying 51,000 but the.  
Ahmad Sheikh: loading on.  
George Varkey: product grid is only 16,000.  
Ahmad Sheikh: Yeah. And still we need to check if, you know, it enabled all after we, you know, navigate to that page. Yes. So there is a problem when we reload it, you know, enable all.  
George Varkey: Also the count is, uh, there something there.  
Ahmad Sheikh: Okay.  
George Varkey: 16,700 and on the inventory grid 51,000.  
Ahmad Sheikh: Yeah, let me disable them all again.  
George Varkey: Can I select only one product? No, don't do full brand. Just select one item from this.  
Ahmad Sheikh: One item. Okay. One.  
George Varkey: uh selected two.  
Ahmad Sheikh: item. There are right now. Let me check if it's just, so it enabled.  
George Varkey: Yeah, it only added those.  
Ahmad Sheikh: those. Right. Right. But there are bugs. I will, you know fix those.  
George Varkey: two. Okay. And now if you go to the product grid, I mean, the inventory grid, only two are showing or all are showing only two.  
Ahmad Sheikh: Now. There is a, yeah, only two are shown. Yeah, there is a problem in the. Bulk. Right. Yeah. So, we are almost there. Just a few fixes made on.  
George Varkey: the Yeah. Yeah. I think we're we're almost there. Pricing the inventory thing, and I think finally would be the.  
Ahmad Sheikh: this.  
George Varkey: user permissions.  
Ahmad Sheikh: Yeah, you can, you know, yeah, there are I know I have set up the permissions as well. Like while you creating user, you can select these permissions. So there are, you know, accountant role. And we have, you know, predefined the know permissions as well, but you can edit them as well if you don't want these and want different ones.  
George Varkey: Okay. No, but I want I wanted like a very specific thing. So, if you go to, uh, so for example, if you go to invoices, can you go to invoices? Uh, yeah, over here. So, as you can see, this profit column is showing in the super admin, which is fine. No, no issues there. But we don't want to show it in the other user permissions then.  
Ahmad Sheikh: Yes. All those noises.  
George Varkey: like if you go to the yeah column manager, click that. Yeah. So basically what I what I was thinking is for super admin, we can edit, manipulate.  
Ahmad Sheikh: Right.  
George Varkey: this. Some of these you cannot edit or manipulate. Right. So the same way for all. For we'll have two user classes: the accountants and accountant and sales. So in the in the accountant, they can same same permissions as super.  
Ahmad Sheikh: Right.  
George Varkey: admin except there's no view for the reports. But in the sales, we we don't want the this column option for the sales team where they can, you know, add or remove some of these columns. So only the fixed columns which are preset. Like we'll change the preset columns, but they cannot add or view additional columns in the sales.  
Ahmad Sheikh: Right? So they can't see the profit, they can't. See the, you know, no all the.  
George Varkey: uh expenses. Yeah. And like. If you go to the right, if you go to the right, the record record expense and calculate profit, that button does not show up for them. This only only available.  
Ahmad Sheikh: financial.  
George Varkey: for accounted and super admin.  
Ahmad Sheikh: Right. So these granular permissions you need in user roles and permissions.  
George Varkey: Yes. Just only that, only that.  
Ahmad Sheikh: Right. Okay. Only that, right. Okay. I will take.  
George Varkey: thing. Yeah.  
Ahmad Sheikh: care of.  
George Varkey: If you want, I can I can tell you what fixed column views to keep for all. Like you have already presets and column rows. I just wanted to change that. So if you want, I can tell you which ones to keep fixed. Uh.  
Ahmad Sheikh: Yeah. Yeah. You can. Yeah, you can send me that in writing, maybe. So.  
George Varkey: as yeah. I'll do that. I'll do that. Then I wanted to show you two bugs that I was facing. If I can share my screen, I'll show. You can see that. So, all right. And also in the sales sales side, no need to show these thing. Only in the super, only in the super ad, that's all.  
Ahmad Sheikh: Uh.  
George Varkey: one. These yeah, pend, yeah, pending, pending. orders, monthly revenue, today's orders, and warranty claims. No need to show this in these cards in the.  
Ahmad Sheikh: these cards, right? Dashboard card, right? In super admin or in.  
George Varkey: super. Okay. So the bug, no, saved in, yeah. Uh, the bug that I was facing is. Good. Uh, yeah. Okay. So as you can see, this is 11750\. Price is tax inclusive, correct? But when you click zero ratings, this subtotal should be 11750\. That should be zero and total 11750\. No.  
Ahmad Sheikh: So if you create that, what happen? So maybe, yeah, maybe it's not reflecting real time, but maybe after you create it, it will show that.  
George Varkey: zero rated VAT, right? So the VAT 5% becomes is. No, it doesn't, it doesn't, it doesn't show. Okay, that's one thing. Second thing.  
Ahmad Sheikh: Okay.  
George Varkey: is add-ons. Okay. So, if I add this add-on, I cannot create a quote. I get an I get an error. But if you go back to quotes, it creates a quote with zero value. So this quote got created but the add-on is not there. Only the five units that we added. But the total value, everything is zero when you add that add-on. That's okay.  
Ahmad Sheikh: Right. All right. Okay. Yeah, sure. Okay. I will stop now. Yes.  
George Varkey: So, uh, and no, I'll show you another thing. Okay, now it will work. This is going, I think. Yeah. Yeah. Okay. To invoice. Oh, I was getting an error when I was converting that one to invoice. No, no, no, wait. I'll show you. No, just now I was getting that. It was for this this invoice. It is a one stock item and one I put a custom item. I created custom item. Uh, convert to Yeah, this one was getting.  
Ahmad Sheikh: Yeah, that I guess few I fixed, uh, you know, in yesterday. This. Okay, these errors are now in the server logs. I will get it from there the details and we will fix this.  
George Varkey: another. Yeah, that was one thing. Um, uh, here also, if you can add the full details: finish, size, bowl, factor, offset. And here the VAT amount is showing zero. The total is correct, but this is showing zero in the other.  
Ahmad Sheikh: in email. Okay. Where it is coming zero? So which email is this one? Code, right. Got the in the attachment. Yeah.  
George Varkey: code, the send, send quote email. And also the over here it shows print and download and the is not showing. And here also the, yeah, okay. That was that. And all right, one more thing here. dashboard, mark as delivered.  
Ahmad Sheikh: Okay.  
George Varkey: mark as deliver, mark as done, until the brows payment is recorded. This is only only in the dashboard. Uh.  
Ahmad Sheikh: Okay. So, right, this functionality is not needed.  
George Varkey: then? Uh, no, because over here when you put marked as delivered, it it is marked as delivered, and but if the payment is pending, it goes to the pending payment section over here. So over here, same thing, right? If you mark as delivered, then the status is delivered, but it remains in the pending paying section. Right? But over here, we cannot mark as.  
Ahmad Sheikh: Right. So you want it should mark as delivered, but if there is a payment pending, it should, you know, okay, okay, got.  
George Varkey: delivered. It'll it'll remain in the pending payment section. It will not be in the pending delivery. It'll just be in the pending payment. Yeah. Yeah. In the dashboard.  
Ahmad Sheikh: it from the dashboard.  
George Varkey: Then another thing was, another thing was, uh, yeah, here order cancel and refunded amount should go to zero. Balance should go to zero.  
Ahmad Sheikh: Okay. When it's cancelled, amount should get zero, right? Balance and amount.  
George Varkey: Okay. Uh, yeah, cancelled and refund. Yeah, cancel and refunded zero and zero, that's one. And then, um, here cancel, uh, yeah. So the other thing was, when I when I cancel the order, the inventory did not get added back to the. So wait, I'll show you. I did some. I changed it from a consignment. I made it here. Okay. So, let's record. Sales. Okay, now we go to invoices. Yeah, this is the one. Um, so here preview. It's five units. This part number from, I think, because. Let me select all of. Okay.  
Ahmad Sheikh: I don't know. I'll do that.  
George Varkey: Yeah. Okay. So, there is zero quantity here. Now, this is the invoice, correct? Yeah. I want to cancel order. Or order. LOS has been cancelled. And I know it deallocated. It's still zero. Yeah. So what we were doing, what we, what I requested was, we get the same return option. Consignment record return. Same same kind of popup when we cancel the order. So the item you can select it and which warehouse and the condition. So for the invoice as well. Yeah. So that was one thing. Uh, yeah, other than that, I didn't find any other issues.  
Ahmad Sheikh: Right. Entry is not, uh, you know, assigned again after canceling the. Right. Okay. I will, you know, try to fix all of these today, then we can go over this tomorrow. Okay. Thank you.  
George Varkey: Yes, perfect. Perfect. Okay, thank you.  


