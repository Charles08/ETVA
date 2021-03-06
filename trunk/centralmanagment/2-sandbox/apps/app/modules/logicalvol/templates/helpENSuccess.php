<div class="help">
<a id="help-lvol"><h1>Logical Volumes</h1></a>
<p>
The operations available to manage logical volumes are the following:
</p>

<ul>
    <li><a href="#help-lvol-add">Add</a></li>
    <li><a href="#help-lvol-rs">Re-size</a></li>
    <li><a href="#help-lvol-rm">Remove</a></li>
    <li><a href="#help-lvol-clone">Clone</a></li>
    <li><a href="#help-lvol-convert">Convert</a></li>
    <li><a href="#help-lvol-scan">Scan <em>Logical Volumes</em></a></li>
</ul>
<br/>
<hr/>

<a id="help-lvol-add"><h1>Add</h1></a>
<p>
To create a logical volume access the sub-context menu on via logical volume grid and then select <em>Add logical volume </em>.
</p>
<p>
In the window we must introduce the desired name, the volume group where the logical volume should be created, and the size.
</p>
<p>
It's also possible define the format of disk (raw, qcow2, qcow, cow and vmdk - raw by default) and configure the percentage of space to use for <em>snapshots</em>
</p>
<a href="#help-lvol"><div>Index</div></a>
<hr/>

<a id="help-lvol-rs"><h1>Re-size</h1></a>
To re-size a logical volume, select the pretended volume and access to the sub-context menu. Then select the option <em>Re-size logical volume</em> which allow us to increase/decrease the size of the logical volume.

<p><b>Note:</b> 
Reducing the size of the logical volume could make existing data unusable. It is your responsibility to check that it is affordable/safe to do the operation.
</p>
<a href="#help-lvol"><div>Index</div></a>
<hr/>


<a id="help-lvol-rm"><h1>Remove</h1></a>
<p>
To remove a logical volume, select the pretended volume and access to the sub-context menu. Then select the option <em>Remove logical volume</em> which allow us to increase/decrease the size of the logical volume. The logical volume will only be removed if you have not assigned to any virtual machine. To verify if is in use, you can pass the mouse over the logical volume and observe the tooltip information.
</p>
<a href="#help-lvol"><div>Index</div></a>
<hr/>

<a id="help-lvol-clone"><h1>Clone</h1></a>
<p>This option creates a new volume with the same size of the selected volume. It's Only available if the logical volume is not in use. In addition to this condition, it is also necessary that there should be a volume group with enough space to receive the new volume.</p>
<p>
If the target volume group is shared, then the changes are propagated to other nodes of the cluster.
</p>
<a href="#help-lvol"><div>Index</div></a>
<hr/>

<a id="help-lvol-convert"><h1>Convert</h1></a>
<p>When we choose this option we can convert the disk to another format (raw, qcow2, qcow, cow and vmdk).</p>
<a href="#help-lvol"><div>Index</div></a>
<hr/>

<a id="help-lvol-scan"><h1>Scan <em><b>Logical Volumes</b></em></h1></a>
<p>On <em>Scan logical volumes</em> it synchronizes <em>logical volumes</em> that are on virtualization agent and they are not registered on <em>Central Management</em>.
It is may be possible that exist <em>logical volumes</em> that are registered on <em>Central Management</em> but really don't exist for some reason.
In this cases, it is possible remove the register from the system and get the <em>logical volumes</em> synchronized with <em>Scan logical volumes</em>.</p>
<a href="#help-lvol"><div>Index</div></a>
<hr/>

</div>
